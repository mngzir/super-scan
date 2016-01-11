<?php

/**
 * Part of the SuperScan package.
 *
 * @package    SuperScan
 * @version    0.0.1
 * @author     joshwhatk
 * @license    MIT
 * @link       http://jwk.me
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class MigrationJoshwhatkSuperScan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            foreach(config('superscan::account_information.relations') as $relation)
            {
                $relation = $this->convertRelation($relation);
                $table->integer($relation.'_id')->unsiged()->index();
            }

            $table->engine = 'InnoDB';
        });

        Schema::create('baseline', function (Blueprint $table) {
            $table->string('file_path', 200);
            $table->char('file_hash', 40);
            $table->char('file_last_modified', 19)->nullable();
            $table->integer('account_id')->nullable()->unsigned();
            $table->timestamps();

            $table->engine = 'InnoDB';

            $table->primary('file_path');
            $table->foreign('account_id')->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('history', function (Blueprint $table) {
            $table->char('stamp', 19)->nullable();
            $table->string('status', 10);
            $table->string('file_path', 200);
            $table->string('hash_org', 40)->nullable()->default(null);
            $table->string('hash_new', 40)->nullable()->default(null);
            $table->char('file_last_modified', 19)->nullable();
            $table->integer('account_id')->unsigned();
            $table->timestamps();

            $table->engine = 'InnoDB';

            $table->foreign('account_id')->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('scanned', function (Blueprint $table) {
            $table->char('scanned', 19)->primary();
            $table->integer('changes', 11)->default(0);
            $table->integer('account_id')->unsigned();

            $table->timestamps();

            $table->engine = 'InnoDB';

            $table->foreign('account_id')->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('scanned');
        Schema::drop('history');
        Schema::drop('baseline');
        Schema::drop('accounts');
    }


    protected function convertRelation($relation)
    {
        return str_singular($relation);
    }
}
