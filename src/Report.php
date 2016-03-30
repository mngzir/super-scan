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

namespace JoshWhatK\SuperScan;

use Illuminate\Support\Facades\Mail;
use JoshWhatK\SuperScan\Contracts\ReportingInterface;

class Report implements ReportingInterface
{
    /**
     * The Scan on which to run the Report
     * @var \JoshWhatK\SuperScan\Support\Scan
     */
    protected $scan;

    protected $messages;

    public function __construct()
    {
        $this->messages = collect([]);
    }

    public function addScan(Scan $scan)
    {
        $this->scan = $scan;
    }

    public function report()
    {
        $baseline_count = $this->scan->baseline->count();
        $this->scan->log($baseline_count." baseline files extracted from database.");

        $account = $this->scan->account;
        $scan = $this->scan;
        $messages = $this->messages;
        $added = $this->scan->added;
        $altered = $this->scan->altered;
        $deleted = $this->scan->deleted;

        $altered_files_text = $this->getFilesText($altered);
        $added_files_text = $this->getFilesText($added);
        $deleted_files_text = $this->getFilesText($deleted);

        Mail::send('super-scan.emails.report', compact(
            $account,
            $scan,
            $messages,
            $added,
            $altered,
            $deleted,
            $altered_files_text,
            $added_files_text,
            $deleted_files_text), function ($m)
        {
            $m->from(config('joshwhatk.super-scan.reporting.from.email'), config('joshwhatk.super-scan.reporting.from.name'));

            $m->to(config('joshwhatk.super-scan.reporting.recipients'))->subject('SuperScan Report');
        });
    }

    public function alert($message, $type = null)
    {
        if(is_null($type))
        {
            $type = 'alert';
        }

        $this->messages = $this->messages->push([
            'content' => $message,
            'type' => $type,
        ]);
    }

    protected function getFilesText($files)
    {
        if($files->isEmpty())
        {
            return 'No Files';
        }

        if($files->count() == 1)
        {
            return '1 File';
        }

        return $files->count().' Files';
    }
}
