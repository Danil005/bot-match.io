<?php

namespace App\Http\Controllers\Commands;

use App;
use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ProfileEditCommand extends Controller
{
    use SendApi, Database;

    private $aboutYou;

    /**
     * :t
     */
    public function __construct()
    {
        $this->aboutYou = new App\Conversations\AboutYou();
    }

    /**
     * Склонения
     *
     * @param $number
     * @param $titles
     * @return string
     */
    private function declOfNum($number, $titles): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $format = $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
        return sprintf($format, $number);
    }

    public function changeName(BotMan $bot)
    {
        $this->setLang($bot);

        $user = $this->users()->where('user_id', $bot->getUser()->getId())->first();

        $question = Question::create(__('telegram.profile.name', ['name' => $user->name]))
            ->fallback('Unable to ask question')
            ->callbackId('ask_name')
            ->addButton(
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile delete')
            );

        return $bot->ask($question, function (Answer $answer) use ($bot) {
            if(str_contains($answer->getValue(), 'edit_profile')) {
                (new App\Conversations\AboutYou())->deleteMessage($bot);
                return true;
            }


            DB::table('bot_users')->where('user_id', $bot->getUser()->getId())->update([
                'name' => trim($answer->getText())
            ]);

            (new App\Conversations\AboutYou())->deleteMessage($bot);
        });
    }

    public function changeAge(BotMan $bot, string $message = '')
    {
        $this->setLang($bot);

        $user = $this->users()->where('user_id', $bot->getUser()->getId())->first();


        $message = !$message ? __('telegram.profile.age', ['age' => $this->declOfNum($user->age, [
            '%d' . __('telegram.years_decl_num.1'),
            '%d' . __('telegram.years_decl_num.3'),
            '%d' . __('telegram.years_decl_num.5')
        ])]) : $message;

        $question = Question::create($message)
            ->fallback('Unable to ask question')
            ->callbackId('ask_name')
            ->addButton(
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile delete')
            );

        return $bot->ask($question, function (Answer $answer) use ($bot) {
            if(str_contains($answer->getValue(), 'edit_profile')) {
                (new App\Conversations\AboutYou())->deleteMessage($bot);
                return true;
            }

            $age = trim($answer->getText());

            if (!is_numeric($age))
                return $this->changeAge($bot, 'telegram.errors.wrong_age');

            $age = intval($age);
            if ($age < 14 || $age > 99)
                return $this->changeAge($bot, 'telegram.errors.wrong_age');

            DB::table('bot_users')->where('user_id', $bot->getUser()->getId())->update([
                'age' => trim($answer->getText())
            ]);

            return (new App\Conversations\AboutYou())->editProfile($bot);
        });
    }

    public function changeGames(BotMan $bot)
    {
        $this->setLang($bot);

        $games = $this->games()->where('user_id', $bot->getUser()->getId())->get();

        $options = array_merge(Keyboard::create()->addRow(
            KeyboardButton::create(($games->contains('game', 'pubg_mobile') ? '✅' : '🚫') . ' PUBG MOBILE')->callbackData('selGames pubg_mobile edit'),
            KeyboardButton::create(($games->contains('game', 'pubg_new_state') ? '✅' : '🚫') . ' PUBG New State')->callbackData('selGames pubg_new_state edit')
        )->addRow(
            KeyboardButton::create(__('telegram.buttons.cancel'))->callbackData('edit_profile delete')
        )->toArray());

        $this->sendMessage($bot, __('telegram.your_game_playing'), $options);
    }

    public function changeCountry(BotMan $bot, string $countryId = '', $message = '')
    {
        $this->setLang($bot);

        if( $countryId != '' ) {
            $this->users()->where('user_id', $bot->getUser()->getId())->update([
                'country' => $countryId
            ]);
            return (new App\Conversations\AboutYou())->editProfile($bot);
        }

        $question = Question::create($message == '' ? __('telegram.your_countries') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries');

        return $bot->ask($question, function (Answer $answer) use($bot, $countryId) {
            $lang = DB::table('bot_users')->where('user_id', $bot->getUser()->getId())->first()->lang;
            \Illuminate\Support\Facades\App::setLocale($lang);

            $country = $answer->getText();

            $countries = DB::table('_countries')
                ->where('title_ru', 'ILIKE', '%'.$country.'%')
                ->orWhere('title_en', 'ILIKE', '%'.$country.'%')
                ->orWhere('title_ua', 'ILIKE', '%'.$country.'%')
                ->limit(5)
                ->get();


            if( $countries->isEmpty() )
                return $this->changeCountry($bot, $countryId, 'telegram.errors.countries_not_found');

            $options = Keyboard::create();

            foreach ($countries as $country) {
                $options = $options->addRow(
                    KeyboardButton::create($lang == 'ru' ? $country->title_ru : $country->title_en)->callbackData("editCountry {$country->country_id}")
                );
            }
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.not_exist_country'))->callbackData("edit_profile country")
            );
            $options = $options->toArray();

            $bot->reply(__('telegram.your_countries_select'), $options);
        });
    }

    public function changeCity(BotMan $bot, string $cityId = '', $message = '')
    {
        $this->setLang($bot);

        if( $cityId != '' ) {
            $this->users()->where('user_id', $bot->getUser()->getId())->update([
                'city' => $cityId
            ]);
            return (new App\Conversations\AboutYou())->editProfile($bot);
        }

        $question = Question::create($message == '' ? __('telegram.your_cities') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries');

        return $bot->ask($question, function (Answer $answer) use($bot, $cityId) {
            $lang = DB::table('bot_users')->where('user_id', $bot->getUser()->getId())->first()->lang;
            \Illuminate\Support\Facades\App::setLocale($lang);
            $user = (new App\Conversations\AboutYou())->users()->where('user_id', $bot->getUser()->getId())->first();
            $city = $answer->getText();

            $message = (new App\Conversations\AboutYou())->sendMessage($bot, '```Загрузка...```', ['parse_mode' => 'MarkdownV2']);
            $cities = DB::table('_cities')->where('country_id', $user->country)
                ->where('title_ru', 'ILIKE', ''.$city.'%')
                ->orWhere('title_en', 'ILIKE', ''.$city.'%')
                ->orWhere('title_ua', 'ILIKE', ''.$city.'%')
                ->limit(5)
                ->get();


            if( $cities->isEmpty() )
                return $this->changeCountry($bot, $cityId, 'telegram.errors.countries_not_found');

            $options = Keyboard::create();

            foreach ($cities as $city) {
                $options = $options->addRow(
                    KeyboardButton::create($lang == 'ru' ? $city->region_ru.', '.$city->title_ru : $city->region_en.', '.$city->title_en)->callbackData("editCity {$city->city_id}")
                );
            }
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.not_exist_city'))->callbackData("edit_profile city")
            );
            $options = $options->toArray();

            (new App\Conversations\AboutYou())->deleteMessage($bot, $message);
            $bot->reply(__('telegram.your_cities_select'), $options);
        });
    }

    public function changeAboutMe(BotMan $bot, $message = '')
    {
        $this->setLang($bot);

        $question = Question::create($message == '' ? __('telegram.about_you') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButton(
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile delete')
            );

        return $bot->ask($question, function (Answer $answer) use ($bot){
            if ($answer->getValue() == 'edit_profile delete') {
                (new App\Conversations\AboutYou())->deleteMessage($bot);
                return true;
            }

            $aboutYou = $answer->getText();

            DB::table('bot_users')->where('user_id', $bot->getUser()->getId())->update([
                'about_you' => trim($aboutYou)
            ]);

            return (new App\Conversations\AboutYou())->editProfile($bot);
        });
    }

    public function changeSex(BotMan $bot, $message = '')
    {
        $this->setLang($bot);

        $question = Question::create($message == '' ? __('telegram.sex') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButtons([
                Button::create(__('telegram.buttons.sex.male'))->value('male'),
                Button::create(__('telegram.buttons.sex.female'))->value('female'),
                Button::create(__('telegram.buttons.sex.decided'))->value('decided'),
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile cancel'),
            ]);

        return $bot->ask($question, function (Answer $answer) use ($bot) {
            if ($answer->isInteractiveMessageReply()) {
                if( $answer->getValue() == 'edit_profile cancel' ) {
                    (new App\Conversations\AboutYou())->deleteMessage($bot);
                    return true;
                }

                (new App\Conversations\AboutYou())->users()->where('user_id', $bot->getUser()->getId())->update([
                    'sex' => $answer->getValue()
                ]);

                return (new App\Conversations\AboutYou())->editProfile($bot);
            }
        });
    }

    public function changeSexPartner(BotMan $bot, $message = '')
    {
        $this->setLang($bot);

        $question = Question::create($message == '' ? __('telegram.sex_partner') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButtons([
                Button::create(__('telegram.buttons.sex.male'))->value('male'),
                Button::create(__('telegram.buttons.sex.female'))->value('female'),
                Button::create(__('telegram.buttons.sex.decided'))->value('decided'),
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile cancel'),
            ]);

        return $bot->ask($question, function (Answer $answer) use ($bot) {
            if ($answer->isInteractiveMessageReply()) {
                if( $answer->getValue() == 'edit_profile cancel' ) {
                    (new App\Conversations\AboutYou())->deleteMessage($bot);
                    return true;
                }

                (new App\Conversations\AboutYou())->users()->where('user_id', $bot->getUser()->getId())->update([
                    'sex_partner' => $answer->getValue()
                ]);

                return (new App\Conversations\AboutYou())->editProfile($bot);
            }
        });
    }

    public function changeFindWhom(BotMan $bot, $message = '')
    {
        $this->setLang($bot);

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(__('telegram.whom_find.people_real_life'))->callbackData('whom_find peopleRealLife')
        )->addRow(
            KeyboardButton::create(__('telegram.whom_find.people_team'))->callbackData('whom_find peopleTeam')
        )->addRow(
            KeyboardButton::create(__('telegram.whom_find.simple_play'))->callbackData('whom_find simplePlay')
        )->addRow(
            KeyboardButton::create(__('telegram.buttons.cancel'))->callbackData('edit_profile delete')
        )->toArray();

        return $this->sendMessage($bot, __('telegram.whom_find.title'), $options);
    }
}
