<?php
namespace Longman\TelegramBot\Commands\UserCommands;

use App\Task;
use App\Telegram\Commands\MagicCommand;
use App\Telegram\Helpers\Week;
use Carbon\Carbon;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

class TasksCommand extends MagicCommand {
    protected $name = 'tasks';
    protected $private_only = false;

    public function execute(){
       dump(Request::sendMessage($this->genMessage([
          'parse_mode' => 'markdown',
          'chat_id' => $this->getMessage()->getChat()->getId()
       ], true, Week::getCurrentWeek())));
    }
    public function onCallback(CallbackQuery $callbackQuery, array $action, array $edited): array{
        if($action[0] == "show") {
            if(isset($action[1]) && is_numeric($action[1])) $action[1] = (bool)(int)$action[1];
            if(isset($action[2]) && is_numeric($action[2])) $action[2] = (int)$action[2];
            dump($action);
            dump(isset($action[2]) ? $action[2] : Week::getCurrentWeek());
            return $this->genMessage($edited, isset($action[1]) && is_bool($action[1]) ? $action[1] : true, isset($action[2]) ? $action[2] : Week::getCurrentWeek());
        }
        return [];
    }

    protected function genMessage(array $base, bool $full, int $week): array {
        $base['text'] = $this->getTasks($full, $week);
        $base['reply_markup'] = new InlineKeyboard(...[
            $full ? new InlineKeyboardButton(['text' => __('tgbot.schedule.toggle_min_btn'), 'callback_data' => 'tasks_show_0']) : new InlineKeyboardButton(['text' => __('tgbot.schedule.toggle_full_btn'), 'callback_data' => 'tasks_show_1'], ),
            [new InlineKeyboardButton(['text' => __('tgbot.tasks.prev_week'), 'callback_data' => "tasks_show_{$full}_".($week-1)]), new InlineKeyboardButton(['text' => __('tgbot.tasks.next_week'), 'callback_data' => "tasks_show_{$full}_".($week+1)]),],
            new InlineKeyboardButton(['text' => __('tgbot.back_toMain_button'), 'callback_data' => 'start'])
        ]);
        return $base;
    }
    
    protected function getTasks(bool $full, ?int &$week = null, ?int $dayOfWeek = null) {
        $currentWeek = Week::getCurrentWeek();
        dump($week);
        if($week === null) $week = $currentWeek;
        if($dayOfWeek === null) $dayOfWeek = Week::getCurrentDeyOfWeek();
    
        $days = [];
        if(!$full){
            if($dayOfWeek >= 5){
                $days[$dayOfWeek] = $week;
                if($dayOfWeek < $dayOfWeek) $days[$dayOfWeek+1] = $week;
                $days[1] = $week+1;
            }elseif ($dayOfWeek < 5){
                $days[$dayOfWeek] = $week;
                $days[$dayOfWeek+1] = $week;
            }
        } else {
            if($currentWeek == $week) $week = $dayOfWeek >= 5 ? $currentWeek+1 : $currentWeek;
            for($day = 1; $day <= 6; $day++) {
                $days[$day] = $week;
            }
        }
        dump(array_values($days));
        $tasks= Task::getByWeek($this->getClassId(), function ($query)use($days){
            return $query->whereIn('tasks.day', $values = array_keys($days))->whereIn('agenda.day', $values);
        }, array_values($days), false);
        dump(array_keys($tasks));
        
        $str = "";
        if(isset($tasks[-1])){
            $tasks[$week] =$tasks[-1];
            unset($tasks[-1]);
        }
    
        foreach ($days as $day => $week){
            $str .= "_".Week::getDayString($day)."_ ".(($currentWeek != $week) ? '('.Week::humanizeDayAndWeek($week, $day).')' : "").PHP_EOL;
            
            if(isset($tasks[$week][$day])){
                foreach ($tasks[$week][$day] as $task) {
                    ++$task['num'];
                    $str .= "{$task['num']}. *{$task['title']}*: _{$task['task']}_" . PHP_EOL; //TODO: add desc
                }
            }else{
                $str .= __('tgbot.schedule.empty').PHP_EOL;
            }
            $str .= PHP_EOL;
        }

        return $str;
    }
}
