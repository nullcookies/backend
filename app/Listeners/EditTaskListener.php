<?php

namespace App\Listeners;

use App\DailyTask;
use App\Events\TaskEdited;
use App\Telegram\Helpers\Week;
use Carbon\Carbon;
use Longman\TelegramBot\Commands\UserCommands\TasksCommand;
use Longman\TelegramBot\Request;

class EditTaskListener{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct() {
        //
    }

    /**
     * Handle the event.
     *
     * @param  TaskEdited  $event
     * @return void
     */
    public function handle(TaskEdited $event) {
        $task = $event->task;
        
        $week = $task->week;
        $dayOfWeek = $task->day;
        $tasks = TasksCommand::getTasks($task->class_id, false, $week,$dayOfWeek , false, true, true);
        
        $daily = DailyTask::where([
           ['class_id', $task->class_id],
           ['week', $week],
           ['dayOfWeek', $dayOfWeek]
        ]); //поскольку dailyTasks включают два дня, два следующих
        dump(json_encode($daily->get()));
    
        foreach ($daily->get() as $day){
            /** @var DailyTask $day */
            
            Request::editMessageText([
               'text' => $tasks,
               'chat_id' => $day->chat_id,
               'message_id' => $day->message_id,
               'parse_mode' => "markdown"
            ]);
    
        }
    }
}