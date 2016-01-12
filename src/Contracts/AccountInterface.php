<?php

/**
 * Part of the SuperScan package.
 *
 * @package    SuperScan
 * @version    0.0.2
 * @author     joshwhatk
 * @license    MIT
 * @link       http://jwk.me
 */

namespace Joshwhatk\SuperScan\Contracts;

interface AccountInterface
{
    /**
     * Get the name of the Server for the Account.
     * @return string
     */
    public function getServerName();

    /**
     * Get the IP Address of the Server for the Account.
     * @return string
     */
    public function getIpAddress();

    /**
     * Get the Webroot of the Website for the Account.
     * @return string
     */
    public function getWebroot();

    /**
     * Get the URL of the Website for the Account.
     * @return string
     */
    public function getUrl();

    /**
     * Get a Collection of excluded file paths.
     * @return \Illuminate\Support\Collection
     */
    public function getExcludedDirectories();
}