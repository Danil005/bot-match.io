<?php

namespace App\Http\Controllers\Commands;

use App;
use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\BotMan;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StartCommand extends Controller
{
    use SendApi, Database;

    public function start(BotMan $bot)
    {
        $user = DB::table('bot_users');
        $userId = $bot->getUser()->getId();
        if (!$user->where('user_id', $userId)->exists()) {
            $user->insert([
                'user_id' => $userId
            ]);

            $options = array_merge(Keyboard::create()->addRow(
                KeyboardButton::create('🇷🇺 Русский')->callbackData('setLang ru'),
                KeyboardButton::create('🇬🇧 English')->callbackData('setLang en')
            )->toArray());

            $bot->reply(__('telegram.hello'), $options);
        } else {
            $this->finished($bot);
        }
    }

    public function setLangHears(BotMan $bot, $lang)
    {
        $user = DB::table('bot_users');
        App::setLocale($lang);

        $user->where('user_id', $bot->getUser()->getId())->update([
            'lang' => $lang
        ]);

        $options = array_merge(Keyboard::create()->addRow(
            KeyboardButton::create(__('telegram.buttons.good'))->callbackData('good')
        )->toArray());

        $this->sendMessage($bot, __('telegram.about_me'), $options);
    }

    public function getDataUser(BotMan $bot)
    {
        $this->setLang($bot);
        $bot->startConversation(new App\Conversations\YourData());
    }

    public function selGame(BotMan $bot, $game, string $edit = '')
    {
        $this->setLang($bot);

        $gameBase = $this->games()->where('user_id', $bot->getUser()->getId())->where('game', $game);
        $gameExist = $gameBase->exists();

        if ($gameExist) {
            $gameBase->delete();
        } else {
            $this->games()->insert([
                'user_id' => $bot->getUser()->getId(),
                'game'    => $game
            ]);
        }

        $games = $this->games()->where('user_id', $bot->getUser()->getId())->get();

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(($games->contains('game', 'pubg_mobile') ? '✅' : '🚫') . ' PUBG MOBILE')
                ->callbackData($edit != 'edit' ? "selGame pubg_mobile" : 'selGames pubg_mobile edit'),
            KeyboardButton::create(($games->contains('game', 'pubg_new_state') ? '✅' : '🚫') . ' PUBG New State')
                ->callbackData($edit != 'edit' ? "selGame pubg_new_state" : 'selGames pubg_new_state edit')
        );

        if( $edit != 'edit' ) {
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.continue'))->callbackData('selCountries')
            );
        } else {
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.save'))->callbackData('edit_profile delete')
            );
        }

        $options = $options->toArray();

        $this->editMessage($bot, __('telegram.your_game_playing'), $options);
    }

    public function selCountries(BotMan $bot)
    {
        $this->setLang($bot);
        $this->deleteMessage($bot);
        $bot->startConversation(new App\Conversations\Countries());
    }

    public function selCities(BotMan $bot, $countryId)
    {
        $this->setLang($bot);
        $this->deleteMessage($bot);
        $bot->startConversation(new App\Conversations\Cities($countryId));
    }

    public function setCountry(BotMan $bot, $countryId)
    {
        $this->users()->where('user_id', $bot->getUser()->getId())->update([
            'country' => $countryId,
        ]);

        $this->setLang($bot);
        $this->deleteMessage($bot);
        $bot->startConversation(new App\Conversations\Cities($countryId));
    }

    public function setCity(BotMan $bot, $cityId)
    {
        $this->users()->where('user_id', $bot->getUser()->getId())->update([
            'city' => $cityId,
        ]);

        $this->setLang($bot);
        $this->deleteMessage($bot);
        $bot->startConversation(new App\Conversations\AboutYou());
    }

    function setWhomFind(BotMan $bot, $whom)
    {
        $this->users()->where('user_id', $bot->getUser()->getId())->update([
            'whom_find' => $whom
        ]);
    }

    public function finished(BotMan $bot)
    {
        $this->setLang($bot);

        $user = $this->users()->where('user_id', $bot->getUser()->getId())->first();

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(__('telegram.buttons.show_questionnaires'))->callbackData('questionnaires')
        )->addRow(
            KeyboardButton::create(__('telegram.buttons.edit_profile'))->callbackData('edit_profiles')
        )->addRow(
            KeyboardButton::create(__('telegram.buttons.not_looking'))->callbackData('not_looking')
        )->toArray();

        $message = __('telegram.main');
        $message .= $user->name . ', ';
        $message .= $this->declOfNum($user->age, [
            '%d' . __('telegram.years_decl_num.1'),
            '%d' . __('telegram.years_decl_num.3'),
            '%d' . __('telegram.years_decl_num.5')
        ]);

        $sex = $user->sex == 'male' ? __('telegram.buttons.sex.male') : __('telegram.buttons.sex.female');
        $sexPartner = $user->sex_partner == 'male' ? __('telegram.buttons.sex.male_r') : __('telegram.buttons.sex.female_r');

        switch ($user->whom_find) {
            case 'peopleRealLife':
                $whomFind = __('telegram.whom_find.people_real_life');
                break;
            case 'peopleTeam':
                $whomFind = __('telegram.whom_find.people_team');
                break;
            case 'simple_play':
                $whomFind = __('telegram.whom_find.simple_play');
                break;
            default:
                $whomFind = '';
                break;
        }
        $country = $this->countries()->where('country_id', $user->country)->first();
        $city = $this->cities($user->country)->where('city_id', $user->city)->first();

        $country = $user->lang == 'ru' ? $country->title_ru : $country->title_en;
        $city = !empty($city) ? ($user->lang == 'ru' ? $city->title_ru : $city->title_en) : '';
        $city = (!empty($city) ? ', ' : '') . $city;

        $message .= ", " . $country . $city;
        $message .= " | " . $sex . "\n" . ($whomFind ? __('telegram.whom_find.i_find') . $whomFind : '');
        $message .= ', ' . $sexPartner;
        $message .= "\n\nОписание анкеты:\n" . trim($user->about_you);

        $this->deleteMessage($bot);
        if($user->avatar) {
            return $this->sendPhoto($bot, $user->avatar, $message, $options);
        } else {
            return $this->sendMessage($bot, $message, $options);
        }
    }
}
