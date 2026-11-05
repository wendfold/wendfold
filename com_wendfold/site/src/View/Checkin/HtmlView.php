<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Site\View\Checkin;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	public $event = null;

	public $profile = null;

	public $attendance = [];

	public $token = '';

	public $user = null;

	public $isCheckedIn = false;

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$input = $app->getInput();
		$this->token = $input->getString('token', '');
		$this->user = $app->getIdentity();

		/** @var \Wendfold\Component\Wendfold\Site\Model\CheckinModel $model */
		$model = $this->getModel();

		$this->event = $model->getEventByToken($this->token);

		if ($this->event && $this->user->id)
		{
			$this->profile = $model->getProfileByUserId($this->user->id);

			if (!empty($this->event->showDetails))
			{
				$this->attendance = $model->getAttendance($this->event->id);
			}

			if ($this->profile)
			{
				$this->isCheckedIn = $model->isCheckedIn($this->event->id, $this->profile->id);
			}
		}

		$this->document->setTitle(Text::_('COM_WENDFOLD_CHECKIN_TITLE'));

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		parent::display($tpl);
	}
}
