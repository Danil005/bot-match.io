<?php
use App\Http\Controllers\BotManController;
use App\Http\Controllers\Commands\StartCommand;
use BotMan\BotMan\BotMan;

$botman = resolve('botman');
echo 'ok';

$botman->hears('/start', StartCommand::class.'@start');

//$botman->hears('Start conversation', BotManController::class.'@startConversation');

/**
 * Callback
 */
$botman->hears('setLang {lang}', StartCommand::class.'@setLangHears');
$botman->hears('good', StartCommand::class.'@getDataUser');

$botman->hears('selGame {game}', StartCommand::class.'@selGame');
$botman->hears('selGames {game} {edit}', StartCommand::class.'@selGame');

$botman->hears('selCountries', StartCommand::class.'@selCountries');
$botman->hears('setCountry {countryId}', StartCommand::class.'@setCountry');
$botman->hears('editCountry {countryId}', \App\Http\Controllers\Commands\ProfileEditCommand::class.'@changeCountry');
$botman->hears('editCity {cityId}', \App\Http\Controllers\Commands\ProfileEditCommand::class.'@changeCity');

$botman->hears('selCities {countryId}', StartCommand::class.'@selCities');
$botman->hears('setCity {cityId}', StartCommand::class.'@setCity');

$botman->hears('whom_find {find}', \App\Conversations\AboutYou::class.'@end');
$botman->hears('edit_profiles', \App\Conversations\AboutYou::class.'@editProfile');
$botman->hears('edit_profile {type}', \App\Conversations\AboutYou::class.'@editProfile');

$botman->hears('finished', StartCommand::class.'@finished');

$botman->hears('questionnaires', \App\Http\Controllers\QuestionnaireController::class.'@get');
$botman->hears('Искать дальше', \App\Http\Controllers\QuestionnaireController::class.'@get');
$botman->hears('❤️', \App\Http\Controllers\QuestionnaireController::class.'@like');
$botman->hears('👎', \App\Http\Controllers\QuestionnaireController::class.'@dislike');
$botman->hears('💌', \App\Http\Controllers\QuestionnaireController::class.'@sendMessageLiked');
$botman->hears('💤', \App\Http\Controllers\QuestionnaireController::class.'@stopLike');
$botman->hears('Моя анкета', StartCommand::class.'@start');
$botman->hears('show_liked', \App\Http\Controllers\QuestionnaireController::class.'@showLiked');