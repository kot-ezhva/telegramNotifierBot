<?php

class Telegram
{
    private $botApiUri = "https://api.telegram.org/bot";
    private $fullBotApiUrl;
    public $token;
    public $webhookUrl;
    public $password;

    public function init()
    {
        $this->fullBotApiUrl = $this->botApiUri . $this->token;
    }

    public function sendMessage($message, $dialogIds = [])
    {
        if($this->webhookStatus()) {
            $method = "/sendMessage";

            if(empty($dialogIds)) {
                if(file_exists(__DIR__ . "/chatIds.txt")) {
                    $dialogIds = file_get_contents(__DIR__ . "/chatIds.txt");
                    $dialogIds = explode(",", $dialogIds);
                }
            }

            if(!empty($dialogIds)) {
                $ch = curl_init($this->fullBotApiUrl . $method);
                foreach ($dialogIds as $id){
                    curl_setopt($ch, CURLOPT_POSTFIELDS, ["chat_id" => $id, "text" => $message]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_exec($ch);
                }

                curl_close($ch);
            }

        } else {
            if($this->setWebhook()) {
                $this->sendMessage($message, $dialogIds);
            }
        }
    }

    private function setWebhook()
    {
        $settingStatus = false;
        $method = "/setwebhook";

        $ch = curl_init($this->fullBotApiUrl . $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "url" => $this->webhookUrl,
            "allowed_updates" => [
                "message"
            ]
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);

        if($result) {
            $result = json_decode($result);

            if($result->result) {
                $settingStatus = true;
            }
        }
        curl_close($ch);

        return $settingStatus;
    }

    private function webhookStatus()
    {
        $status = false;
        $infoUrl = "/getWebhookInfo";

        $ch = curl_init($this->fullBotApiUrl . $infoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $result = curl_exec($ch);

        if($result) {
            $result = json_decode($result);
            $url = $result->result->url;
            if($url && $url == $this->webhookUrl) {
                $status = true;
            }
        }
        curl_close($ch);

        return $status;
    }

    public function webhookHandler()
    {
        $content = file_get_contents("php://input");
        $update = json_decode($content);
        $message = $update->message;
        $chatId = $message->chat->id;
        $text = $message->text;

        if(!$this->checkChatId($chatId)){
            if($text !== $this->password) {
                $this->sendMessage("Неверный пароль. Пробуйте еще :-)", [$chatId]);
            } else {
                if(!$this->saveChatId($chatId)) {
                    $this->sendMessage("Проблема с правами на файл. Обратитесь к администратору", [$chatId]);
                } else {
                    $this->sendMessage("Вы успешно подписались на обновления сайта " . $_SERVER["HTTP_HOST"], [$chatId]);
                }
            }
        }
    }

    private function checkChatId($id)
    {
        $status = false;
        if(file_exists(__DIR__ . "/chatIds.txt")) {
            $savedIds = file_get_contents(__DIR__ . "/chatIds.txt");
            $savedIds = explode(",", $savedIds);
            $status = in_array($id, $savedIds);
        }

        return $status;
    }

    private function saveChatId($id)
    {
        if(file_exists(__DIR__ . "/chatIds.txt")) {
            $savedIds = file_get_contents(__DIR__ . "/chatIds.txt");
            $savedIds = explode(",", $savedIds);
            array_push($savedIds, $id);
        } else {
            $savedIds = [$id];
        }

        return file_put_contents(__DIR__ . "/chatIds.txt", implode(",", $savedIds));
    }
}