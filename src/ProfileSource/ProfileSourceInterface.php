<?php

namespace Drutiny\ProfileSource;

use Drutiny\LanguageManager;
use Drutiny\Profile;

/**
 * Provide policies for Drutiny to use.
 */
interface ProfileSourceInterface
{

    /**
     * The short name that defines this source.
     */
    public function getName():string;

    /**
     * Return a list of policy definitions.
     *
     * Each definition should contain keys for name and description.
     * The definition is passed to the ::load() function to load a Policy object.
     *
     * @param \Drutiny\LanguageManager $languageManager
     */
    public function getList(LanguageManager $languageManager):array;

    /**
     * Load a Drutiny\Policy object.
     *
     * @param array $definition
     *  A definition array generated by PolicySourceInterface::getList().
     */
    public function load(array $definition):Profile;

    /**
     * Get the weight of the source.
     *
     * @return int a number to indicate which priority the policies should take
     *  if duplicates are provided by other sources.
     */
    public function getWeight():int;
}
