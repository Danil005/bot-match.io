<?php


namespace App\Utils;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Exceptions\Core\BadMethodCallException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

trait SendApi
{
    public $lang = '';

    /**
     * @return string
     */
    public function randomLoading(): string
    {
        for ($i = 1; $i <= 5; $i++) {
            $messages[] = __('telegram.loading.text_' . $i);
        }
        $index = rand(0, count($messages) - 1);
        return $messages[$index];
    }

    public function setLang(BotMan $bot)
    {
        $user = DB::table('bot_users');
        $userId = $bot->getUser()->getId();
        $this->lang = $user->where('user_id', $userId)->first()->lang;
        App::setLocale($this->lang);
    }

    public function editMessage(BotMan $bot, string $message, array $options)
    {
        $botMessages = $bot->getMessages();
        $options = array_merge([
            'chat_id'    => $bot->getUser()->getId(),
            'message_id' => $botMessages[count($botMessages) - 1]->getPayload()['message_id'],
            'text'       => $message,
        ], $options);

        try {
            $bot->sendRequest('editMessageText', $options);
        } catch (BadMethodCallException $e) {
            echo $e->getMessage();
        }
    }

    public function deleteMessage(BotMan $bot, $messageId = null)
    {
        if ($messageId) {
            $message = json_decode($messageId->getContent());
            if ($message->ok) {
                $message = $message->result->message_id;
            }
        } else {
            $botMessages = $bot->getMessages();
            $message = $botMessages[count($botMessages) - 1]->getPayload()['message_id'];
        }

        $options = [
            'chat_id'    => $bot->getUser()->getId(),
            'message_id' => $message,
        ];

        try {
            $bot->sendRequest('deleteMessage', $options);
        } catch (BadMethodCallException $e) {
            echo $e->getMessage();
        }
    }

    public function sendMessage(BotMan $bot, string $message, array $options)
    {
        $options = array_merge([
            'chat_id' => $bot->getUser()->getId(),
            'text'    => $message,
        ], $options);

        try {
            return $bot->sendRequest('sendMessage', $options);
        } catch (BadMethodCallException $e) {
            echo $e->getMessage();
        }
    }

    public function removeKeyboard(BotMan $bot, array $options = [])
    {
        $options = array_merge([
            'remove_keyboard' => true,
        ], $options);

        try {
            return $bot->sendRequest('ReplyKeyboardRemove', $options);
        } catch (BadMethodCallException $e) {
            echo $e->getMessage();
        }
    }

    public function sendPhoto(BotMan $bot, string $photo, string $message = '', array $options = [])
    {
        $options = array_merge([
            'chat_id' => $bot->getUser()->getId(),
            'photo'   => $photo,
        ], $options);

        if ($message) {
            $options = array_merge([
                'caption' => $message,
            ], $options);
        }

        try {
            return $bot->sendRequest('sendPhoto', $options);
        } catch (BadMethodCallException $e) {
            echo $e->getMessage();
        }
    }
}