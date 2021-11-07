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

class Cities extends Conversation
{
    use SendApi, Database;

    protected $countryId = 0;

    public function __construct($countryId)
    {
        $this->countryId = $countryId;
    }

    public function askCities(string $message = '')
    {
        $this->setLang($this->bot);
        $this->deleteMessage($this->bot);

        $question = Question::create($message == '' ? __('telegram.your_cities') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries');

        return $this->ask($question, function (Answer $answer) {
            $this->setLang($this->bot);
            $city = $answer->getText();

            $message = $this->sendMessage($this->bot, '```Загрузка...```', ['parse_mode' => 'MarkdownV2']);
            $cities = $this->cities($this->countryId)
                ->where('title_ru', 'ILIKE', ''.$city.'%')
                ->orWhere('title_en', 'ILIKE', ''.$city.'%')
                ->orWhere('title_ua', 'ILIKE', ''.$city.'%')
                ->limit(5)
                ->get();

            if( $cities->isEmpty() )
                return $this->askCities('telegram.errors.cities_not_found');

            $options = Keyboard::create();

            foreach ($cities as $city) {
                $options = $options->addRow(
                    KeyboardButton::create($this->lang == 'ru' ? $city->region_ru.', '.$city->title_ru : $city->region_en.', '.$city->title_en)->callbackData("setCity {$city->city_id}")
                );
            }
            $options = $options->addRow(
                KeyboardButton::create(__('telegram.buttons.not_exist_city'))->callbackData("selCities {$this->countryId}")
            );
            $options = $options->toArray();

            $this->deleteMessage($this->bot, $message);
            $this->bot->reply(__('telegram.your_cities_select'), $options);
        });
    }

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askCities();
    }
}
