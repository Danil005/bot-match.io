<?php

namespace App\Conversations;

use App\Http\Controllers\Commands\ProfileEditCommand;
use App\Models\BotUsers;
use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use DB;
use http\Client\Curl\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Storage;

class AboutYou extends Conversation
{
    use SendApi, Database;

    private $profileEdit;

//    public function __construct()
//    {
//        $this->profileEdit = new ProfileEditCommand();
//    }

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

    public function askAboutYou(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.about_you') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButton(
                Button::create(__('telegram.buttons.skip'))->value('skip')
            );

        return $this->ask($question, function (Answer $answer) {
            if ($answer->getValue() == 'skip') {
                return $this->askSex();
            }

            $aboutYou = $answer->getText();

            $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
                'about_you' => trim($aboutYou)
            ]);

            $this->askSex();
        });
    }

    public function askSex(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.sex') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButtons([
                Button::create(__('telegram.buttons.sex.male'))->value('male'),
                Button::create(__('telegram.buttons.sex.female'))->value('female'),
                Button::create(__('telegram.buttons.sex.decided'))->value('decided'),
            ]);

        return $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
                    'sex' => $answer->getValue()
                ]);

                $this->askSexPartner();
            }
        });
    }

    public function askSexPartner(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.sex_partner') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButtons([
                Button::create(__('telegram.buttons.sex.male'))->value('male'),
                Button::create(__('telegram.buttons.sex.female'))->value('female'),
                Button::create(__('telegram.buttons.sex.no_matter'))->value('no_matter'),
            ]);

        return $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
                    'sex_partner' => $answer->getValue()
                ]);

                $this->askPhoto();
            }
        });
    }

    public function askPhoto(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.give_photo') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButton(
                Button::create(__('telegram.buttons.skip'))->value('skip')
            );

        return $this->askForImages($question, function ($image) {
            $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
                'avatar' => $image[0]->getPayload()['file_id']
            ]);
            return $this->whomFind();
        }, function (Answer $answer) {
            if ($answer->getValue() == 'skip') {
                return $this->whomFind();
            }

            return $this->askPhoto('telegram.errors.wrong_photo');
        });
    }

    public function whomFind(string $message = '')
    {
        $this->setLang($this->bot);

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(__('telegram.whom_find.people_real_life'))->callbackData('whom_find peopleRealLife')
        )->addRow(
            KeyboardButton::create(__('telegram.whom_find.people_team'))->callbackData('whom_find peopleTeam')
        )->addRow(
            KeyboardButton::create(__('telegram.whom_find.simple_play'))->callbackData('whom_find simplePlay')
        )->toArray();

        return $this->sendMessage($this->bot, __('telegram.whom_find.title'), $options);
    }

    public function end(BotMan $bot, $find = null)
    {
        $this->setLang($bot);
        if ($find != null) {
            $this->users()->where('user_id', $bot->getUser()->getId())->update([
                'whom_find' => $find
            ]);
        }
        $user = $this->users()->where('user_id', $bot->getUser()->getId())->first();

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(__('telegram.buttons.all_right'))->callbackData('finished')
        )->addRow(
            KeyboardButton::create(__('telegram.buttons.edit_profile'))->callbackData('edit_profiles')
        )->toArray();

        $message = __('telegram.finished');
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

    public function editProfile(BotMan $bot, $type = '')
    {
        $this->setLang($bot);
        $user = $this->users()->where('user_id', $bot->getUser()->getId())->first();

        if (!$type) {
            $options = Keyboard::create()->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.name'))->callbackData('edit_profile name'),
                KeyboardButton::create(__('telegram.buttons.edit.age'))->callbackData('edit_profile age')
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.games'))->callbackData('edit_profile games'),
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.country'))->callbackData('edit_profile country'),
                KeyboardButton::create(__('telegram.buttons.edit.city'))->callbackData('edit_profile city'),
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.about_me'))->callbackData('edit_profile about_me'),
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.sex'))->callbackData('edit_profile sex'),
                KeyboardButton::create(__('telegram.buttons.edit.sex_partner'))->callbackData('edit_profile sex_partner'),
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.photo'))->callbackData('edit_profile photo'),
                KeyboardButton::create(__('telegram.buttons.edit.find_whom'))->callbackData('edit_profile find_whom'),
            )->addRow(
                KeyboardButton::create(__('telegram.buttons.edit.back'))->callbackData('edit_profile back'),
            )->toArray();

            $message = __('telegram.edit_profile');
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

        if($type == 'delete') {
            $this->deleteMessage($bot);
            return 1;
        }

        if ($type == 'back') {
            return $this->end($bot);
        }

        if($type == 'name') {
            return (new ProfileEditCommand())->changeName($bot);
        }

        if($type == 'age') {
            return (new ProfileEditCommand())->changeAge($bot);
        }

        if($type == 'games') {
            (new ProfileEditCommand())->changeGames($bot);
            return 1;
        }

        if($type == 'country') {
            (new ProfileEditCommand())->changeCountry($bot);
            return 1;
        }

        if($type == 'city') {
            (new ProfileEditCommand())->changeCity($bot);
            return 1;
        }

        if($type == 'about_me') {
            (new ProfileEditCommand())->changeAboutMe($bot);
            return 1;
        }

        if($type == 'sex') {
            (new ProfileEditCommand())->changeSex($bot);
            return 1;
        }

        if($type == 'sex_partner') {
            (new ProfileEditCommand())->changeSexPartner($bot);
            return 1;
        }

        if($type == 'photo') {
            $bot->startConversation(new askPhoto());
            return 1;
        }

        if($type == 'find_whom') {
            (new ProfileEditCommand())->changeFindWhom($bot);
            return 1;
        }
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askAboutYou();
    }
}
