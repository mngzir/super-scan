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

namespace Joshwhatk\SuperScan\Database;

use Joshwhatk\SuperScan\Support\File;
use Illuminate\Database\Eloquent\Model;
use Joshwhatk\SuperScan\Database\Account;
use Illuminate\Database\Eloquent\Collection;

class BaselineFile extends Model
{
    protected $fillable = ['path', 'hash', 'last_modified'];

    public function scopeAccount($query, Account $account)
    {
        return $query->where('account_id', $account->id);
    }

    public function toFiles(Collection $baseline_files)
    {
        $files_collection = collect([]);

        foreach ($baseline_files as $baseline_file) {
            $files_collection->push([
                $baseline_file->file_path => new File($basline_file)
            ]);
        }

        return $files_collection;
    }

    public static function createFromFile(File $file, Account $account)
    {
        $baseline = new static;
        $baseline->fill($file->toArray($account));
        $baseline->save();

        return $baseline;
    }

    public static function updateFromFile(File $file, Account $account)
    {
        $baseline = static::where('path', $file->path)->account($account)->first();
        $baseline->fill($file->toArray());
        $baseline->save();

        return $baseline;
    }
}