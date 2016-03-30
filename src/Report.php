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

        Mail::send('super-scan.emails.report', compact(
            $account,
            $scan,
            $messages,
            $added,
            $altered,
            $removed,
            $altered_files_text,
            $added_files_text,
            $removed_files_text), function ($m)
        {
            $m->from(config('joshwhatk.super-scan.reporting.from.email'), config('joshwhatk.super-scan.reporting.from.name'));

            $m->to(config('joshwhatk.super-scan.reporting.recipients'))->subject('SuperScan Report');
        });

        return view('super-scan.emails.report')->with(compact(
            $account,
            $scan,
            $messages,
            $added,
            $altered,
            $removed,
            $altered_files_text,
            $added_files_text,
            $removed_files_text)
        );
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
}
