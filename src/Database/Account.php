<?php

/**
 * Part of the SuperScan package.
 *
 * @package    SuperScan
 * @version    0.0.4
 * @author     joshwhatk
 * @license    MIT
 * @link       http://jwk.me
 */

namespace JoshWhatK\SuperScan\Database;

use Illuminate\Database\Eloquent\Model;
use JoshWhatK\SuperScan\Contracts\AccountInterface;

class Account extends Model implements AccountInterface
{
    protected $fillable = ['name'];

    /**
     * Get the name of the Server for the Account.
     * @return string
     */
    public function getServerName()
    {
        //
    }

    /**
     * Get the IP Address of the Server for the Account.
     * @return string
     */
    public function getIpAddress()
    {
        //
    }

    /**
     * Get the Webroot of the Website for the Account.
     * @return string
     */
    public function getScanDirectory()
    {
        //
    }

    /**
     * Get the URL of the Website for the Account.
     * @return string
     */
    public function getUrl()
    {
        //
    }

    /**
     * Get a Collection of excluded file paths.
     * @return \Illuminate\Support\Collection
     */
    public function getExcludedDirectories()
    {
        //
    }
}
