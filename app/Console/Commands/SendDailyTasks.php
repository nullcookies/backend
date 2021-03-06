<?php
namespace App\Console\Commands;
require app_path('Telegram/Commands/TasksCommand.php');


use App\ClassM;
use App\DailyTask;
use App\Telegram\Helpers\Week;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Longman\TelegramBot\Commands\UserCommands\TasksCommand;
use Longman\TelegramBot\Request;
use PhpTelegramBot\Laravel\PhpTelegramBotContract;

class SendDailyTasks extends Command{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'daily:sendAll';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle(PhpTelegramBotContract $bot) {
		dump('daily');
		dump(date('d.m.Y'));
		$dt = Carbon::now();
		$startdt = (clone $dt)->startOfDay();
		dump($startdt);
		dump($dt);
		$diff = $startdt->diffInSeconds($dt);
		dump($diff);
		dump(config('app.timezone'));

		$chats = ClassM::select('classes.id as class_id', 'notify_chat_id', 'user_owner', 'chat_id') //chat_id для фикса
			->where([
				['notify_time', '<=', $diff+90],
//                ['notify_time', '>=', $diff-10]
			])
			->get();

		dump(json_encode($chats));
		foreach ($chats as $chat){
			$week = Week::getCurrentWeek();
			$dayOfWeek = Week::getCurrentDayOfWeek();

			$tasks = TasksCommand::getTasks($chat['class_id'], false, $week, $dayOfWeek, false, true, false);
			dump($week);
			dump($dayOfWeek);
			$daily_task = DailyTask::select('message_id')->where([
				["class_id", "=", $chat['class_id']],
				['dayOfWeek', "=", $dayOfWeek],
				['week', $week]
			])->first();


			if(!isset($daily_task->message_id)){
				$chat_id = $chat['notify_chat_id'] == null ? $chat['user_owner'] : $chat['notify_chat_id'];
				$resp = Request::sendMessage([
					'chat_id' => $chat_id,
					'text' => $tasks,
					'parse_mode' => 'markdown'
				]);

				if($resp->getErrorCode() == 400){ //group to super group
					$migrate_to_chat_id = $resp->getProperty('parameters', ['migrate_to_chat_id' => null])['migrate_to_chat_id'];
					if($migrate_to_chat_id != null){
						if($chat['chat_id'] == $chat['notify_chat_id']){
							$chat['chat_id'] = $chat['notify_chat_id'] = $migrate_to_chat_id;
						}else{
							$chat['notify_chat_id']  = $migrate_to_chat_id;
						}

						ClassM::find($chat['class_id'])->update([
							'chat_id' => $chat['chat_id'],
							'notify_chat_id' => $chat['notify_chat_id']
						]);
						return;
					}
				}
				if($resp->isOk()) {
					DailyTask::insert([
						'class_id' => $chat['class_id'],
						'chat_id' => $chat_id,
						'message_id' => $resp->getResult()->message_id,
						'dayOfWeek' => $dayOfWeek,
						'week' => $week
					]);
				}else{
					dump($resp);
				}
			}
		}

		return 'ok';
	}
}
