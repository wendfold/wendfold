<?php
/**
 * Wendfold - Email Blast Controller
 *
 * Handles two tasks:
 *   display()  - Show the compose form (GET)
 *   send()     - Validate inputs, build recipient list, send mail, redirect (POST)
 *
 * Authorization: requires wendfold.emailblast (Super Users and Administrators
 * by default). Sending also requires HTTP POST and a valid CSRF token.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\EmailblastHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\PostGuard;
use Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel;

class EmailblastController extends BaseController
{
	/**
	 * Default task: render the compose form.
	 *
	 * @return  void
	 */
	public function display($cachable = false, $urlparams = [])
	{
		$app  = Factory::getApplication();

		if (!EmailblastHelper::canSend())
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php', false));

			return;
		}

		$view = $this->getView('Emailblast', 'Html');

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel $model */
		$model = $this->getModel('Emailblast');
		$view->setModel($model, true);
		$view->display();
	}

	/**
	 * previewcount() task  -  lightweight JSON endpoint.
	 *
	 * Returns {"count": N} for a given filter so the template JS can
	 * update the recipient preview badge without a full page reload.
	 * Requires HTTP POST, a valid CSRF token, and wendfold.emailblast.
	 *
	 * @return  void
	 */
	public function previewcount()
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app  = Factory::getApplication();

		if (!EmailblastHelper::canSend())
		{
			jexit(Text::_('JERROR_ALERTNOAUTHOR'));
		}

		$audience = $this->audienceFromInput();

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel $model */
		$model = $this->getModel('Emailblast');
		$count = count($model->getRecipients($audience));

		$app->setHeader('Content-Type', 'application/json; charset=utf-8');
		echo json_encode(['count' => $count]);
		$app->close();
	}

	/**
	 * send() task  -  POST handler.
	 *
	 * Validates CSRF token, reads form inputs, queries recipients,
	 * sends one email per recipient via Joomla's mailer, then redirects
	 * back to the compose form with a success or error flash message.
	 *
	 * @return  void
	 */
	public function send()
	{
		// -----------------------------------------------------------------
		// 1. POST + CSRF + blast permission
		// -----------------------------------------------------------------
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app   = Factory::getApplication();

		if (!EmailblastHelper::canSend())
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php', false));

			return;
		}

		$app   = Factory::getApplication();
		$input = $app->getInput();

		// -----------------------------------------------------------------
		// 2. Read and sanitise form inputs
		// -----------------------------------------------------------------
		$subject  = trim($input->getString('subject', ''));
		$body     = trim($input->getString('body', ''));
		$audience = $this->audienceFromInput();

		// -----------------------------------------------------------------
		// 3. Basic validation
		// -----------------------------------------------------------------
		if (empty($subject))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EMAILBLAST_ERROR_NO_SUBJECT'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=emailblast', false));

			return;
		}

		if (empty($body))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EMAILBLAST_ERROR_NO_BODY'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=emailblast', false));

			return;
		}

		// -----------------------------------------------------------------
		// 4. Build recipient list
		// -----------------------------------------------------------------
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel $model */
		$model      = $this->getModel('Emailblast');
		$recipients = $model->getRecipients($audience);

		if (empty($recipients))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EMAILBLAST_ERROR_NO_RECIPIENTS'), 'warning');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=emailblast', false));

			return;
		}

		// -----------------------------------------------------------------
		// 5. Send emails
		// -----------------------------------------------------------------
		$mailer    = clone Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
		$fromEmail = (string) $app->get('mailfrom', '');
		$fromName  = (string) $app->get('fromname', 'Wendfold');

		$sent   = 0;
		$errors = 0;

		foreach ($recipients as $recipient)
		{
			if (empty($recipient->email))
			{
				continue;
			}

			$mail = clone $mailer;
			$mail->isHtml(false);
			$mail->setFrom($fromEmail, $fromName);
			$mail->addRecipient($recipient->email, $recipient->name);
			$mail->setSubject($subject);
			$mail->setBody($body);

			if (EmailblastHelper::attemptSend($mail, $recipient))
			{
				$sent++;
			}
			else
			{
				$errors++;
			}
		}

		// -----------------------------------------------------------------
		// 6. Redirect with result message
		// -----------------------------------------------------------------
		if ($errors === 0)
		{
			$app->enqueueMessage(
				Text::sprintf('COM_WENDFOLD_EMAILBLAST_SEND_SUCCESS', $sent),
				'success'
			);
		}
		else
		{
			$app->enqueueMessage(
				Text::sprintf('COM_WENDFOLD_EMAILBLAST_SEND_PARTIAL', $sent, $errors),
				'warning'
			);
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=emailblast', false));
	}

	/**
	 * Read the compose-form audience and keep it inside the form's options.
	 *
	 * @return  string  One of EmailblastModel::AUDIENCES.
	 *
	 * @since   1.0.0
	 */
	private function audienceFromInput(): string
	{
		$audience = Factory::getApplication()->getInput()->getCmd('audience', 'all');

		if (!in_array($audience, EmailblastModel::AUDIENCES, true))
		{
			return 'all';
		}

		return $audience;
	}
}
