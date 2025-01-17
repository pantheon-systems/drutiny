<?php

namespace Drutiny\Audit\Drupal;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

/**
 * Audit the first row returned from a SQL query.
 * @Token(
 *  name = "result",
 *  description = "The comparison operator to use for the comparison.",
 *  type = "string"
 * )
 * @Token(
 *  name = "results",
 *  description = "The record set.",
 *  type = "string"
 * )
 */
class SqlResultAudit extends AbstractAnalysis
{

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        $this->addParameter(
            'query',
            static::PARAMETER_REQUIRED,
            'The SQL query to run. Can use the audit context for variable replace. E.g. {drush.db-name}.',
        );
        $this->addParameter(
            'db_level_query',
            static::PARAMETER_OPTIONAL,
            '',
            false
        );
    }

    /**
     *
     */
    public function gather(Sandbox $sandbox)
    {
        $query = $sandbox->getParameter('query');
        $db_level_query = $sandbox->getParameter('db_level_query');
        if (!$db_level_query) {
          if (!preg_match_all('/^SELECT( DISTINCT)? (.*) FROM/', $query, $fields)) {
            throw new \Exception("Could not parse fields from SQL query: $query.");
          }
          $fields = array_map('trim', explode(',', $fields[2][0]));
          foreach ($fields as &$field) {
            if ($idx = strpos($field, ' as ')) {
              $field = substr($field, $idx + 4);
            }
            elseif (preg_match('/[ \(\)]/', $field)) {
              throw new \Exception("SQL query contains an non-table field without an alias: '$field.'");
            }
          }
        }

        // Migrate 2.x queries to 3.x
        $query = strtr($query, [
          ':db-name' => '{drush.db-name}',
          ':default_collation' => '{default_collation}'
        ]);
        $query = $this->interpolate($query);
        $this->logger->debug("Running SQL query '{query}'", ['query' => $query]);
        $result = $this->target->getService('drush')
          ->sqlq($query)
          ->run(function ($output) {
              $data = explode(PHP_EOL, $output);
              array_walk($data, function (&$line) {
                  $line = array_map('trim', explode("\t", $line));
                  if (empty($line) || count(array_filter($line)) == 0) {
                      $line = false;
                  }
              });
              return array_filter($data);
          });

        $fields = $this->getFieldsFromSql($query);
        if (!empty($fields)) {
            $result = array_map(function ($row) use ($fields) {
                return array_combine($fields, $row);
            },
            $result);
        }
        $this->set('count', count($result));
        $this->set('results', $result);
        $this->set('first_row', array_shift($result));
    }

    protected function getFieldsFromSql($query):array
    {
      // If we can parse fields out of the SQL query, we can make the result set
      // become and associative array.
        if (!preg_match_all('/^SELECT( DISTINCT)? (.*) FROM/', $query, $fields)) {
            return [];
        }
        return array_map(function ($field) {
              $field = trim($field);

              // If the field has an alias, use that instead.
            if ($idx = strpos($field, ' as ')) {
                $field = substr($field, $idx + 4);
            }

              // If the field is a function without an alias, raise a warning.
            if (preg_match('/[ \(\)]/', $field)) {
                $this->logger->warning("SQL query contains an non-table field without an alias: '$field.'");
            }
              return $field;
        },
          explode(',', $fields[2][0]));
    }
}
