<?php

namespace App\Conversations;

use App\Utils\Database;
use App\Utils\SendApi;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;

class askPhoto extends Conversation
{

    use SendApi, Database;

    public function askPhoto(string $message = '')
    {
        $this->setLang($this->bot);

        $question = Question::create($message == '' ? __('telegram.give_photo') : __($message))
            ->fallback('Unable to ask question')
            ->callbackId('ask_countries')
            ->addButton(
                Button::create(__('telegram.buttons.cancel'))->value('edit_profile delete')
            );

        return $this->askForImages($question, function ($image) {
            $this->users()->where('user_id', $this->bot->getUser()->getId())->update([
                'avatar' => $image[0]->getPayload()['file_id']
            ]);
            return (new AboutYou())->editProfile($this->bot);
        }, function (Answer $answer) {
            if ($answer->getValue() == 'edit_profile delete') {
                $this->deleteMessage($this->bot);
                return true;
            }

            return $this->askPhoto('telegram.errors.wrong_photo');
        });
    }
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->askPhoto();
    }
}
