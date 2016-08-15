<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

$deploy = new plgSystemKickDeploy();

$deploy->gitPull();

class plgSystemKickDeploy
{
	protected $config;

	protected $rawPost = null;

	protected $payload = null;

	protected $errorMail = null;

	protected $infoMail = null;

	public function __construct()
	{
		$config = __DIR__ . "/deploy.json";
		$json = file_get_contents($config);

		$this->config = json_decode($json);
	}

	/**
	 * After route.
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public function gitPull() {
			$this->errorMail = explode(';', $this->config->errorMail);
			$this->infoMail = explode(';', $this->config->infoMail);

			set_error_handler(function($severity, $message, $file, $line) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			});

			set_exception_handler(function($e) {
				header('HTTP/1.1 500 Internal Server Error');
				echo "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
				die();
			});

			if ($this->config->git->checkHookSecret && !is_null($hookSecret = $this->config->git->hookSecret))
			{
				$this->checkSecret($hookSecret);
			}

			$this->checkContentType();
			$this->setPayload();
			$this->handleGitHubEvent();
	}

	protected function checkSecret($hookSecret)
	{
		if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
			throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
		} elseif (!extension_loaded('hash')) {
			throw new \Exception("Missing 'hash' extension to check the secret code validity.");
		}

		list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
		if (!in_array($algo, hash_algos(), TRUE)) {
			throw new \Exception("Hash algorithm '$algo' is not supported.");
		}

		$this->rawPost = file_get_contents('php://input');
		if ($hash !== hash_hmac($algo, $this->rawPost, $hookSecret)) {
			throw new \Exception('Hook secret does not match.');
		}
	}

	protected function checkContentType()
	{
		if (!isset($_SERVER['CONTENT_TYPE'])) {
			throw new \Exception("Missing HTTP 'Content-Type' header.");
		} elseif (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
			throw new \Exception("Missing HTTP 'X-Github-Event' header.");
		}
	}

	protected function setPayload()
	{
		switch ($_SERVER['CONTENT_TYPE']) {
			case 'application/json':
				$json = $this->rawPost ?: file_get_contents('php://input');
				break;

			case 'application/x-www-form-urlencoded':
				$json = $_POST['payload'];
				break;

			default:
				throw new \Exception("Unsupported content type: $_SERVER[HTTP_CONTENT_TYPE]");
		}

		$this->payload = json_decode($json);
	}

	protected function handleGitHubEvent()
	{
		switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
			case 'ping':
				$this->sendMail($this->errorMail, 'Github Ping', '<pre>'. print_r($this->payload) .'</pre>');
				break;

			case 'push':
				try {
					$this->pull($this->payload);

				} catch ( Exception $e ) {
					$msg = $e->getMessage();
					$this->sendMail($this->errorMail, $msg, ''.$e);
				}
				break;

			default:
				header('HTTP/1.0 404 Not Found');
				echo "Event:$_SERVER[HTTP_X_GITHUB_EVENT] Payload:\n";
				print_r($this->payload); # For debug only. Can be found in GitHub hook log.
				die();
		}
	}

	protected function sendMail($recipient, $subject, $body, $from = false, $fromName = 'KickDeploy')
	{
		require_once __DIR__ . '/class.phpmailer.php';
		$mailer = new PHPMailer();

		$mailer->Subject = stripslashes($subject);

		$mailer->Body = trim(preg_replace('/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i', '', $body));
		$mailer->ContentType = 'text/html';

		foreach($recipient as $email)
		{
			$mailer->AddCC($email, '');
		}


		if ($from)
		{
			$mailer->setFrom($from,$fromName);
		}

		// Send the Mail
		$mailer->Send();
	}

	protected function pull($payload)
	{
		$git = $this->config->git->git_path;
		$repo = $this->config->git->repo;
		$branch = $this->config->git->branch;
		$remote = $this->config->git->remote;
		$infoSubject = $this->config->infoSubject;

		if ($payload->repository->url == 'https://github.com/' . $repo
			&& $payload->ref == 'refs/heads/' . $branch)
		{

			$output = shell_exec($git. ' pull ' . $remote . ' ' . $branch . ' 2>&1; echo $?');

			// prepare and send the notification email
			if ($this->config->sendInfoMails) {
				// send mail to someone, and the github user who pushed the commit
				$body = '<p>The Github user <a href="https://github.com/'
					. $payload->pusher->name .'">@' . $payload->pusher->name . '</a>'
					. ' has pushed to ' . $payload->repository->url
					. ' and consequently'
					. '.</p>';

				$body .= '<p>Here\'s a brief list of what has been changed:</p>';
				$body .= '<ul>';
				foreach ($payload->commits as $commit) {
					$body .= '<li>'.$commit->message.'<br />';
					$body .= '<small style="color:#999">added: <b>'.count($commit->added)
						.'</b> &nbsp; modified: <b>'.count($commit->modified)
						.'</b> &nbsp; removed: <b>'.count($commit->removed)
						.'</b> &nbsp; <a href="' . $commit->url
						. '">read more</a></small></li>';
				}
				$body .= '</ul>';
				$body .= '<p>What follows is the output of the script:</p><pre>';
				$body .= nl2br($output). '</pre>';
				$body .= '<p>Cheers, <br/>Github Webhook Endpoint</p>';

				$this->sendMail($this->infoMail, $infoSubject, $body, $payload->pusher->email, $payload->pusher->name);
			}

			return true;
		}
	}
}
