<?php

namespace JoshWhatK\SuperScan\Database;

/**
 * Part of the SuperScan package.
 *
 * @package    SuperScan
 * @version    1.0.2
 * @author     joshwhatk
 * @license    MIT
 * @link       http://jwk.me
 */

use Illuminate\Database\Eloquent\Model;
use JoshWhatK\SuperScan\Contracts\AccountInterface;

class HistoryRecord extends Model
{
    protected $fillable = ['status', 'path', 'baseline_hash', 'latest_hash', 'last_modified', 'account_id'];

    public function scopeAccount($query, AccountInterface $account)
    {
        return $query->where('account_id', $account->id);
    }
}
