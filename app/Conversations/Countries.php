<?php

namespace App\Conversations;

use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use DB;
use Illuminate\Foundation\Inspiring;

class Countries extends Conversation
{
    use SendApi, Database;


    public function askCountries(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.your_countries') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries');

        return $this->ask($question, function (Answer $answer) {
            $this->setLang($this->bot);
            $country = $answer->getText();

            $countries = $this->countries()
                ->where('title_ru', 'ILIKE', '%'.$country.'%')
                ->orWhere('title_en', 'ILIKE', '%'.$country.'%')
                ->orWhere('title_ua', 'ILIKE', '%'.$country.'%')
                ->limit(5)
                ->get();


            if( $countries->isEmpty() )
                return $this->askCountries('telegram.errors.countries_not_found');

            $options = Keyboard::create();

            foreach ($countries as $country) {
                $options = $options->addRow(
                    KeyboardButton::create($this->lang == 'ru' ? $country->title_ru : $country->title_en)->callbackData("setCountry {$country->country_id}")
                );
            }
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.not_exist_country'))->callbackData("selCountries")
            );
            $options = $options->toArray();

            $this->bot->reply(__('telegram.your_countries_select'), $options);
        });
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askCountries();
    }
}
