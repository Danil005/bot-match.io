<?php

namespace App\Conversations;

use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use Illuminate\Foundation\Inspiring;

class YourData extends Conversation
{
    use SendApi, Database;

    protected $name = '';
    protected $age = 0;

    public function askName()
    {
        $this->setLang($this->bot);

        $question = Question::create(__('telegram.your_name'))
            ->fallback('Unable to ask question')
            ->callbackId('ask_name');

        return $this->ask($question, function (Answer $answer) {
            $this->name = $answer->getText();
            $this->askAge();
        });
    }

    public function askAge(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.your_age', ['name' => $this->name]) : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_age');

        return $this->ask($question, function (Answer $answer) {
            $this->age = $answer->getText();

            if (!is_numeric($this->age))
                return $this->askAge('telegram.errors.wrong_age');

            $this->age = intval($this->age);
            if ($this->age < 14 || $this->age > 99)
                return $this->askAge('telegram.errors.wrong_age');

            $this->askGames();
        });
    }

    public function askGames()
    {
        $this->setLang($this->bot);
        $games = $this->games()->where('user_id', $this->bot->getUser()->getId())->get();

        $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
            'name' => $this->name,
            'age'  => $this->age
        ]);

        $options = Keyboard::create()->addRow(
            KeyboardButton::create(($games->contains('game', 'pubg_mobile') ? '✅' : '🚫') . ' PUBG MOBILE')->callbackData('selGame pubg_mobile'),
            KeyboardButton::create(($games->contains('game', 'pubg_new_state') ? '✅' : '🚫') . ' PUBG New State')->callbackData('selGame pubg_new_state')
        );
        if (count($games) > 0) {
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.continue'))->callbackData('selCountries')
            );
        }
        $options = $options->toArray();

        $this->bot->reply(__('telegram.your_game_playing'), $options);
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askName();
    }
}
