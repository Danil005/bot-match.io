<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Commands\StartCommand;
use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use Illuminate\Http\Request;
use App\Conversations\ExampleConversation;
use Illuminate\Support\Facades\Cache;

class QuestionnaireController extends Controller
{
    use SendApi, Database;

    public function get(BotMan $bot, $isMain = true)
    {
        $me = $this->me($bot);
        $this->setLang($bot);


        $this->sendMessage($bot, $this->randomLoading(), ['parse_mode' => 'MarkdownV2']);

        $matching = json_decode(json_encode($this->matching()->where('user_id', $me->user_id)
            ->where(function ($query) {
                $query->where('solution', '!=', 2)
                    ->orWhere('solution', '!=', 0);
            })
            ->get(['user_id_partner'])->toArray()), true);

        $users = $this->users()
            ->where('bot_users.user_id', '!=', $me->user_id)
            ->where('whom_find', $me->whom_find)
            ->where('sex', $me->sex_partner)
            ->whereNotIn(
                'user_id',
                $matching
            )
            ->get();

        if (count($users) == 0) {
            $option = Keyboard::create('keyboard')->addRow(
                KeyboardButton::create(__('telegram.buttons.main'))
            )->resizeKeyboard(true)->oneTimeKeyboard(true)->toArray();

            $this->sendMessage($bot, __('telegram.partner_not_found'), $option);
        } else {
            $user = json_decode(json_encode($users[0]), true);
            Cache::put('user.questionnaire.' . $bot->getUser()->getId(), $user['user_id'], 60);
            $options = array_merge(Keyboard::create('keyboard')->addRow(
                KeyboardButton::create('❤️')->callbackData('like ' . $user['user_id']),
                KeyboardButton::create('👎')->callbackData('dislike ' . $user['user_id']),
                KeyboardButton::create('💌')->callbackData('sendMessage ' . $user['user_id']),
                KeyboardButton::create('💤')->callbackData('stopLike')
            )->resizeKeyboard(true)->oneTimeKeyboard(true)->toArray(), []);

            $message = "";
            $message .= $user['name'] . ', ';
            $message .= $this->declOfNum($user['age'], [
                '%d' . __('telegram.years_decl_num.1'),
                '%d' . __('telegram.years_decl_num.3'),
                '%d' . __('telegram.years_decl_num.5')
            ]);

            $sex = $user['sex'] == 'male' ? __('telegram.buttons.sex.male') : __('telegram.buttons.sex.female');
            $sexPartner = $user['sex_partner'] == 'male' ? __('telegram.buttons.sex.male_r') : __('telegram.buttons.sex.female_r');

            switch ($user['whom_find']) {
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
            $country = $this->countries()->where('country_id', $user['country'])->first();
            $city = $this->cities($user['country'])->where('city_id', $user['city'])->first();

            $country = $user['lang'] == 'ru' ? $country->title_ru : $country->title_en;
            $city = !empty($city) ? ($user['lang'] == 'ru' ? $city->title_ru : $city->title_en) : '';
            $city = (!empty($city) ? ', ' : '') . $city;

            $message .= ", " . $country . $city;
            $message .= " | " . $sex . "\n" . ($whomFind ? __('telegram.whom_find.i_find') . $whomFind : '');
            $message .= ', ' . $sexPartner;
            $message .= "\n\nОписание анкеты:\n" . trim($user['about_you']);

            if ($user['avatar']) {
                return $this->sendPhoto($bot, $user['avatar'], $message, $options);
            } else {
                return $this->sendMessage($bot, $message, $options);
            }
        }
    }

    public function like(BotMan $bot)
    {
        $this->setLang($bot);
        $liked = Cache::get('user.questionnaire.liked.' . $bot->getUser()->getId());
        $userId = Cache::get('user.questionnaire.' . $bot->getUser()->getId());

        if (!isset($liked) && $liked !== 'yes') {
            $this->matching()->insert([
                'user_id'         => $bot->getUser()->getId(),
                'user_id_partner' => $userId,
                'solution'        => 1,
                'message'         => null
            ]);

            $count = $this->matching()->where('user_id_partner', $userId)
                ->where('solution', 1)->count();

            $options = array_merge(
                Keyboard::create()->addRow(
                    KeyboardButton::create(__('telegram.buttons.show_like'))->callbackData('show_liked')
                )->toArray(),
                [
                    'chat_id' => $userId
                ]
            );

            $this->sendMessage($bot, __('telegram.have_like', ['count' => $count . $this->declOfNum($count, [
                    ' человеку',
                    ' людям',
                    ' людям'
                ])]), $options);
            return $this->get($bot, false);
        } else {
            $this->matching()->where('user_id', $userId)->where('user_id_partner', $bot->getUser()->getId())->update([
                'solution' => 2
            ]);

            return $this->showLiked($bot);
        }
    }

    public function dislike(BotMan $bot)
    {
        $userId = Cache::get('user.questionnaire.' . $bot->getUser()->getId());
        $this->matching()->insert([
            'user_id'         => $bot->getUser()->getId(),
            'user_id_partner' => $userId,
            'solution'        => 0,
            'message'         => null
        ]);

        return $this->get($bot, false);
    }

    public function sendMessageLiked(BotMan $bot)
    {
        $this->setLang($bot);
        $userId = Cache::get('user.questionnaire.' . $bot->getUser()->getId());

        $question = Question::create(__('telegram.text_send_message'))->addButton(
            Button::create(__('telegram.buttons.cancel'))->value('cancelSendMessage')
        );

        $bot->ask($question, function (Answer $answer) use ($userId, $bot) {
            if ($answer->getValue() == 'cancelSendMessage') {
                (new QuestionnaireController())->deleteMessage($bot);
                return;
            }

            $message = trim($answer->getText());
            (new QuestionnaireController())->matching()->insert([
                'user_id'         => $bot->getUser()->getId(),
                'user_id_partner' => $userId,
                'solution'        => 1,
                'message'         => $message
            ]);

            return (new QuestionnaireController())->get($bot, false);
        });
    }

    public function stopLike(BotMan $bot)
    {
        $this->removeKeyboard($bot);
        (new StartCommand())->start($bot);
    }


    public function showLiked(BotMan $bot, $isMain = true)
    {
        $me = $this->me($bot);
        $this->setLang($bot);

        $this->sendMessage($bot, $this->randomLoading(), ['parse_mode' => 'MarkdownV2']);

        $matching = json_decode(json_encode($this->matching()
            ->where('user_id_partner', $me->user_id)
            ->where('solution', 1)
            ->get(['user_id'])
            ->toArray()), true);

        $users = $this->users()
            ->whereIn(
                'user_id',
                $matching
            )
            ->get();

        if (count($users) == 0) {
            $option = Keyboard::create('keyboard')->addRow(
                KeyboardButton::create(__('telegram.buttons.like')),
                KeyboardButton::create(__('telegram.buttons.main'))
            )->resizeKeyboard(true)->oneTimeKeyboard(true)->toArray();
            Cache::put('user.questionnaire.liked.' . $bot->getUser()->getId(), "no", 60 * 24 * 365);
            $this->sendMessage($bot, __('telegram.like_next_text'), $option);
        } else {
            $user = json_decode(json_encode($users[0]), true);
            Cache::put('user.questionnaire.' . $bot->getUser()->getId(), $user['user_id'], 60);
            Cache::put('user.questionnaire.liked.' . $bot->getUser()->getId(), "yes", 60 * 24 * 365);
            $options = array_merge(Keyboard::create('keyboard')->addRow(
                KeyboardButton::create('❤️')->callbackData('like ' . $user['user_id']),
                KeyboardButton::create('👎')->callbackData('dislike ' . $user['user_id']),
                KeyboardButton::create('💌')->callbackData('sendMessage ' . $user['user_id']),
                KeyboardButton::create('💤')->callbackData('stopLike')
            )->resizeKeyboard(true)->oneTimeKeyboard(true)->toArray(), []);

            $message = "";
            $message .= $user['name'] . ', ';
            $message .= $this->declOfNum($user['age'], [
                '%d' . __('telegram.years_decl_num.1'),
                '%d' . __('telegram.years_decl_num.3'),
                '%d' . __('telegram.years_decl_num.5')
            ]);

            $sex = $user['sex'] == 'male' ? __('telegram.buttons.sex.male') : __('telegram.buttons.sex.female');
            $sexPartner = $user['sex_partner'] == 'male' ? __('telegram.buttons.sex.male_r') : __('telegram.buttons.sex.female_r');

            switch ($user['whom_find']) {
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
            $country = $this->countries()->where('country_id', $user['country'])->first();
            $city = $this->cities($user['country'])->where('city_id', $user['city'])->first();

            $country = $user['lang'] == 'ru' ? $country->title_ru : $country->title_en;
            $city = !empty($city) ? ($user['lang'] == 'ru' ? $city->title_ru : $city->title_en) : '';
            $city = (!empty($city) ? ', ' : '') . $city;

            $message .= ", " . $country . $city;
            $message .= " | " . $sex . "\n" . ($whomFind ? __('telegram.whom_find.i_find') . $whomFind : '');
            $message .= ', ' . $sexPartner;
            $message .= "\n\nОписание анкеты:\n" . trim($user['about_you']);

            if ($user['avatar']) {
                return $this->sendPhoto($bot, $user['avatar'], $message, $options);
            } else {
                return $this->sendMessage($bot, $message, $options);
            }
        }
    }
}
