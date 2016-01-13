<?php

namespace Joshwhatk\SuperScan;

/**
 * Part of the SuperScan package.
 *
 * @package    SuperScan
 * @version    0.0.4
 * @author     joshwhatk
 * @license    MIT
 * @link       http://jwk.me
 */

use \Log;
use \Carbon\Carbon;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Joshwhatk\SuperScan\Report;
use Joshwhatk\SuperScan\Support\File;
use Joshwhatk\SuperScan\Database\BaselineFile;
use Joshwhatk\SuperScan\Database\HistoryRecord;
use Joshwhatk\SuperScan\Database\Scan as FilesScan;
use Joshwhatk\SuperScan\Contracts\AccountInterface;

class Scan
{
    /**
     * The Account for which the SuperScan is being run.
     *
     * @var \Joshwhatk\Database\Account
     */
    protected $account;

    /**
     * Initialize the array for the `baseline` table.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $baseline;

    /**
     * Initialize the array for the current file scan.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $current;

    /**
     * Intitialize the differences arrays.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $added;
    protected $altered;
    protected $deleted;

    /**
     * The Application's environment
     * @var string
     */
    protected $environment;

    /**
     * Whether or not this is the first time for this account.
     *
     * @var boolean
     */
    protected $first_scan = false;

    /**
     * The time that the scan was started.
     *
     * @var array
     */
    protected $timestamps = [];

    /**
     * The Report to run for the current Scan
     *
     * @var \Joshwhatk\SuperScan\Report
     */
    protected $report;

    /**
     * A Collection of file paths and extensions to be excluded.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $exclusions = [
        'directories' => [],
        'extensions' => [],
    ];

    /**
     * A list of whitelisted extensions if the extensions are whitelisted.
     *
     * @var null or \Illuminate\Support\Collection
     */
    protected $only_extensions = null;

    /**
     * The file iterator.
     *
     * @var \RecursiveIteratorIterator
     */
    protected $iterator;

    /**
     * The default configuration for this package.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $config;

    public function __construct(AccountInterface $account, Report $report)
    {
        $this->report = $report;
        $this->environment = config('app.env');
        $this->createConfig(config('joshwhatk.super_scan'));

        $this->account = $account;
        $this->getExcludedExtensions();
        $this->getExcludedDirectories();

        //-- Initialize Arrays
        $this->baseline = collect([]);
        $this->current = collect([]);

        $this->added = collect([]);
        $this->altered = collect([]);
        $this->deleted = collect([]);
    }

    protected function createConfig($config)
    {
        $this->config = collect([
            'scan_extensionless' => $config['defaults']['extensions']['scan_extensionless'],
            'extensions' => $config['defaults']['extensions'],
            'directories' => $config['defaults']['directories']['blacklist'],
        ]);
    }

    public static function run(AccountInterface $account)
    {
        $scan = new static($account, new Report);

        $scan->initialize();
        $scan->determineBaseline();
        $scan->scanDirectory();
        $scan->handleDeletedFiles();
        $scan->complete();

        $scan->report->addScan($scan);
        $scan->report->report();
    }

    private function initialize()
    {
        $last_scanned_record = $this->getLastScanTime();

        if (is_null($last_scanned_record)) {
            $this->first_scan = true;
        }

        //-- Set the start after the first database query has returned
        $this->timestamps['started'] = new Carbon;
    }

    private function determineBaseline()
    {
        $baselines = BaselineFile::account($this->account)
            ->orderBy('file_path', 'asc')->get();

        //-- convert to File::class
        $this->baseline = $baselines->toFiles($baselines);

        if ($this->baseline->isEmpty() && !$this->first_scan) {
            $this->alert(
                "**Probable hack**  Empty baseline table!  (ALL baseline files are missing or deleted)!"
            );
        }
    }

    private function scanDirectory()
    {
        $recursive_directory_iterator = new RecursiveDirectoryIterator($this->account->getWebroot());
        $this->iterator = new RecursiveIteratorIterator($recursive_directory_iterator);

        while($this->iterator->valid())
        {
            $this->checkDirectoriesAndFiles();
        }
    }

    private function handleDeletedFiles()
    {
        $this->deleted = $this->getDeletedFiles();

        foreach ($this->deleted as $file_path => $file) {
            //-- delete file from baseline table
            $baseline = BaselineFile::where('path', $file_path)->account($this->account)->first();
            $baseline->delete();

            $this->saveDeletedFileToHistory($file_path);
        }
    }

    private function complete()
    {
        $this->timestamps['completed'] = new Carbon;
        $this->dump();
    }

    protected function saveDeletedFileToHistory($file_path)
    {
        $historyRecord = new HistoryRecord;

        $historyRecord->fill([
            'status' => 'Deleted',
            'path' => $file_path,
            'baseline_hash' => $this->deleted[$file_path]['hash'],
            'last_modified' => $this->deleted[$file_path]['last_modified'],
            'account_id' => $this->account->id,
        ]);

        $historyRecord->save();
    }

    protected function getDeletedFiles()
    {
        return $this->baseline->diff($this->current);
    }

    protected function checkDirectoriesAndFiles()
    {
        //  Not in Dot AND not in $skip (prohibited) directories
        if(! $this->directoryIsSkippable())
        {
            //  Get or set file extension ('' vs null)
            $extension = $this->setFileExtension();

            if($this->extensionIsAllowed($extension))
            {
                $file_path = $this->cleanPath($iterator->key());

                //-- add current file
                $this->current->push([$file_path => new File($file_path)]);

                //-- if the file was added
                $this->handleNewFile($file_path);

                //-- if the file was altered
                $this->handleAlteredFile($file_path);
            }
        }
        $this->iterator->next();
    }

    protected function handleNewFile($file_path)
    {
        //-- it is added if baseline doesn't contain the $file_path
        if(! $this->baseline->contains($file_path))
        {
            $this->added->push([
                $file_path => $this->current[$file_path]
            ]);

            //-- insert added file into baseline table
            BaselineFile::createFromFile($this->current[$file_path], $this->account);

            if(! $this->first_scan)
            {
                return $this->saveAddedFileToHistory($file_path);
            }
        }
    }

    protected function handleAlteredFile($file_path)
    {
        if($this->baseline->contains($file_path)
           &&
           ($this->baseline[$file_path]['hash'] != $this->current[$file_path]['hash']
            ||
            $this->baseline[$file_path]['last_modified'] != $this->current[$file_path]['last_modified'])
        )
        {
            $this->altered->push([
                $file_path => [$this->current[$file_path]]
            ]);

            //-- add the baseline_hash
            $this->altered[$file_path]['baseline_hash'] = $this->baseline[$file_path]['hash'];

            //-- update altered file in baseline table
            BaselineFile::updateFromFile($this->current[$file_path], $this->account);

            $this->saveAlteredFileToHistory($file_path);
        }
    }

    protected function saveAlteredFileToHistory($file_path)
    {
        $historyRecord = new HistoryRecord;

        $historyRecord->fill([
            'status' => 'Altered',
            'path' => $file_path,
            'baseline_hash' => $this->altered[$file_path]['baseline_hash'],
            'latest_hash' => $this->altered[$file_path]['hash'],
            'last_modified' => $this->altered[$file_path]['last_modified'],
            'account_id' => $this->account->id,
        ]);

        $historyRecord->save();
    }

    protected function saveAddedFileToHistory($file_path)
    {
        $historyRecord = new HistoryRecord;

        $historyRecord->fill([
            'status' => 'Added',
            'path' => $file_path,
            'latest_hash' => $this->added[$file_path]['hash'],
            'last_modified' => $this->added[$file_path]['last_modified'],
            'account_id' => $this->account->id,
        ]);

        $historyRecord->save();
    }

    protected function cleanPath($path)
    {
        return str_replace(chr(92),chr(47),$path);
    }

    protected function extensionIsAllowed($extension)
    {
        // extension is empty and extensionless are not scanned
        if($extension === '' && !$this->config['scan_extensionless'])
        {
            return false;
        }

        //-- extensions is not whitelisted and the extension is in that array
        if($this->extensionIsBlacklisted($extension))
        {
            return false;
        }

        if(! $this->extensionIsWhitelisted())
        {
            return false;
        }

        return true;
    }

    protected function extensionIsBlacklisted($extension)
    {
        //-- the extensions is not whitelisted and the blacklist contains it
        if(!$this->whitelistIsSet()
           &&
           $this->exclusions['extensions']->contains($extension))
        {
            return true;
        }

        //-- otherwise it isn't blacklisted
        return false;
    }

    protected function extensionIsWhitelisted($extension)
    {
        //-- if whitelist is set and it is in only extensions
        if($this->whitelistIsSet() && $this->only_extensions->contains($extension))
        {
            return true;
        }

        //-- otherwise, it is not whitelisted
        return false;
    }

    protected function getExcludedExtensions()
    {
        if(! $this->whitelistIsSet())
        {
            $this->exclusions['extensions'] = collect($this->config['extensions']['blacklist']);
        }
    }

    /**
     * Returns true if the extensions whitelist contains extensions
     * @return boolean
     */
    protected function whitelistIsSet()
    {
        $whitelist = $this->config['extensions']['whitelist'];

        //-- return false if the whitelist is empty
        if($whitelist === [])
        {
            return false;
        }

        //-- set up the only_extensions property
        if(is_null($this->only_extensions))
        {
            $this->only_extensions = collect($whitelist);
        }

        return true;
    }

    protected function getExcludedDirectories()
    {
        $this->exclusions['directories'] = collect($this->config['directories']);

        //-- add any excluded directories specific to this account
        $account_exclusions = collect($this->account->getExcludedDirectories());
        if(! $account_exclusions->isEmpty())
        {
            $this->exclusions['directories']->merge($account_exclusions->all());
        }
    }

    protected function getLastScanTime()
    {
        return FilesScan::account($this->account)
            ->orderBy('created_at', 'desc')->limit(1)->get();
    }

    protected function directoryIsSkippable()
    {
        return $this->iterator->isDot() || $this->exclusions['directories']->contains($this->iterator->getSubPath());
    }

    protected function setFileExtension()
    {
        if (is_null(pathinfo($this->iterator->key(), PATHINFO_EXTENSION)))
        {
            return '';
        }

        return strtolower(pathinfo($this->iterator->key(), PATHINFO_EXTENSION));
    }

    protected function log($message)
    {
        if ($this->environment === 'local') {
            Log::info($message);
        }
    }

    protected function dump()
    {
        if ($this->environment === 'local') {
            $this->log(['current_files' => $this->current]);
            $this->log(['baseline_files' => $this->baseline]);
            $this->log(['added_files' => $this->added]);
            $this->log(['altered_files' => $this->altered]);
            $this->log(['deleted_files' => $this->deleted]);
        }
    }

    protected function alert($message)
    {
        $this->report->alert($message);
    }
}
