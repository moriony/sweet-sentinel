<?php
namespace Models;

use App\Model\AbstractModel;
use Models\Notifier\Exception\InvalidText;
use Models\Project\Exception\PhoneListNotFound;
use Symfony\Component\Validator\Constraints;

class Notifier extends AbstractModel
{
    protected static $ALARM_TITLE = "Внимание! С проектом %s случилась беда!";
    protected static $INVALID_TEXT_MESSAGE = "Сообщение не должно быть пустым, максимальная длина %d символов";

    protected $min = 1;
    protected $max = 150;

    /**
     * @param string $project
     * @param $subject
     * @param string $text
     * @throws Notifier\Exception\InvalidText
     * @param string $subject
     */
    public function alarm($project, $subject, $text)
    {
        /**
         * @var Project $projectModel
         */
        $projectModel = $this->getModelsRepository()->getProject();

        $constraints =  array(
            new Constraints\NotBlank,
            new Constraints\Length(array('min' => $this->min, 'max' => $this->max))
        );
        $errors = $this->validator()->validateValue($text, $constraints);
        if(count($errors)) {
            throw new InvalidText(sprintf(self::$INVALID_TEXT_MESSAGE, $this->max));
        }

        $emailList = $projectModel->getEmailList($project);
        $transport = new \Swift_Transport_SimpleMailInvoker();
        foreach($emailList as $email) {
            $transport->mail($email, $subject, $text, sprintf('From: %s', $this->site('noreply_email')));
        }

        try {
            $phoneList = $projectModel->getPhoneList($project);
        } catch(PhoneListNotFound $e) {
            $phoneList = array();
        }
        $a1sms = $this->a1sms();
        foreach($phoneList as $phone) {
            $a1sms->send($phone, $subject);
        }
    }
}