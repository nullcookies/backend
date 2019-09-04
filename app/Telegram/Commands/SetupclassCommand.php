<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use App\ClassM;
use App\Telegram\Commands\MagicCommand;
use App\User;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;

class SetupclassCommand extends MagicCommand {
	public $name = 'setupclass';
	public function execute() {}

	public function onCallback(CallbackQuery $callbackQuery, array $action, array $edited): array {
		$anwser = ['text' => __('tgbot.callback_answer')];

		if ($action[0] == 'start'){
			$edited['text'] = __('tgbot.class.start');
			$edited['reply_markup'] = new InlineKeyboard(
				new InlineKeyboardButton(['text' => __('tgbot.class.confirm_button'), 'callback_data' => 'setupclass_step1'])
			);
		}elseif ($action[0] == 'step1'){
			if(!isset($action[1])){
				$edited['text'] = __('tgbot.class.step1');
				$edited['reply_markup'] = new InlineKeyboard(
					new InlineKeyboardButton(['text' => __('tgbot.class.school_button'), 'callback_data' => 'setupclass_step2']),
					new InlineKeyboardButton(['text' => __('tgbot.class.another_button'), 'callback_data' => 'setupclass_step1_ERR'])
				);
			}else if($action[1] == "ERR"){
				$edited['text'] = __('tgbot.class.another_desc');
				$edited['reply_markup'] = new InlineKeyboard(
					new InlineKeyboardButton(['text' => __('tgbot.back_button'), 'callback_data' => 'setupclass_step1'])
				);
			}
		}elseif ($action[0] == 'step2'){
			$edited['text'] = __('tgbot.class.step2');

			$keyboard = [[], []];
			for($i = 5; $i < 12; $i++){
				if($i < 9) $keyboard[0][] = new InlineKeyboardButton(['text' => (string)$i, 'callback_data' => "setupclass_selected_{$i}"]);
				else $keyboard[1][] = new InlineKeyboardButton(['text' => (string)$i, 'callback_data' => "setupclass_selected_{$i}"]);

			}
			$keyboard[] = new InlineKeyboardButton(['text' => __('tgbot.back_button'), 'callback_data' => 'setupclass_step1']);


			$edited['reply_markup'] = new InlineKeyboard(...$keyboard);
		}elseif ($action[0] == 'selected'){
			if (is_numeric($class_num = $action[1])){
				$class = new ClassM();
				$class->class_num = $class_num;
				$class->user_owner = $this->getUser()->id;
				$class->chat_id = $this->getMessage()->getChat()->getId();
				$class->save();



				$conv = $this->getConversation();
				if(isset($conv->notes['finished_query'])){
					/** @var MagicCommand $cmd */
					$cmd = $this->getTelegram()->getCommandObject(($query = $conv->notes['finished_query'])[0]);
					$edited = $cmd->onCallback($callbackQuery, $query[1], $edited);
				}else{
					$edited['text'] = "ERR";
				}
			}
		}elseif ($action[0] == "bindchat"){
		    $conv = $this->getConversation();
		    if (!isset($action[1])){
		        $conv->notes['waitAddToChat'] = $this->getClass()->id;
		        $conv->update();
		        $edited['text'] = __('tgbot.class.bind_chat_instruction');
		        $edited['reply_markup'] = new InlineKeyboard(
                    new InlineKeyboardButton([
                        'text' => __('tgbot.back_toMain_button'),
                        'callback_data' => 'setupclass_bindchat_reset'
                    ])
                );
            }else if ($action[1] == 'reset'){
		        if(isset($conv->notes['waitAddToChat'])) unset($conv->notes['waitAddToChat']);
		        $conv->update();
                $callbackQuery->answer($anwser);
            
                return $this->getTelegram()->getCommandObject('start')->onCallback($callbackQuery, [], $edited);
            }
        }

		$callbackQuery->answer($anwser);

		return $edited;
	}
	public function onMessage(): void {

	}
}
