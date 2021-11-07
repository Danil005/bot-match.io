<?php


namespace App\Utils;

use BotMan\BotMan\BotMan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait Database
{
    /**
     * @return Builder
     */
    public function users(): Builder
    {
        return DB::table('bot_users');
    }

    /**
     * @return Builder
     */
    public function matching(): Builder
    {
        return DB::table('matching');
    }

    /**
     * @return Builder
     */
    public function games(): Builder
    {
        return DB::table('user_games');
    }

    /**
     * @return Builder
     */
    public function countries(): Builder
    {
        return DB::table('_countries');
    }

    /**
     * @return Builder
     */
    public function cities($countryId): Builder
    {
        return DB::table('_cities')->where('country_id', $countryId);
    }

    public function me(BotMan $bot)
    {
        return $this->users()->where('user_id', $bot->getUser()->getId())->first();
    }
}