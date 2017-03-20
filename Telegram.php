<?php

class Telegram
{
    public $botApiUri = "https://api.telegram.org/bot";
    public $chatIdFilename = __DIR__ . DIRECTORY_SEPARATOR . "chatIds.txt";
    public $token;
    public $webhookUrl;
    public $password;
    private $fullBotApiUrl;

    public function init()
    {
        $this->fullBotApiUrl = $this->botApiUri . $this->token;

        foreach ($this as $key => $value) {
            if(!$value) {
                $this->throwError("Не указано свойство " . $key);
            }
        }
    }

    public function sendMessage($message, $dialogIds = [])
    {
        if($this->webhookStatus()) {
            $method = "/sendmessage";

            if(empty($dialogIds)) {
                if(file_exists($this->chatIdFilename)) {
                    $dialogIds = file_get_contents($this->chatIdFilename);
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
        $infoUrl = "/getwebhookinfo";

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
    
    private function checkChatId($id)
    {
        $status = false;
        if(file_exists($this->chatIdFilename)) {
            $savedIds = file_get_contents($this->chatIdFilename);
            $savedIds = explode(",", $savedIds);
            $status = in_array($id, $savedIds);
        }

        return $status;
    }

    private function saveChatId($id)
    {
        if(file_exists($this->chatIdFilename)) {
            $savedIds = file_get_contents($this->chatIdFilename);
            $savedIds = explode(",", $savedIds);
            array_push($savedIds, $id);
        } else {
            $savedIds = [$id];
        }

        return file_put_contents($this->chatIdFilename, implode(",", $savedIds));
    }

    private function throwError($message = "")
    {
        throw new Exception("Ошибка! " . $message . " в компоненте " . get_class($this));
    }
}