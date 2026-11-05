<?php

/**
 * Wendfold - Profile Edit Controller (Site)
 *
 * Handles the profile edit form submission:
 *   1. CSRF token validation
 *   2. Guest redirect guard
 *   3. Photo upload handling (type + size validation, safe file name)
 *   4. POST data sanitization and pass-through to model
 *   5. Success/error flash redirect
 *
 * SECURITY NOTE:
 *   profile_id in POST is passed to the model but the model resolves
 *   the true profile ID from the DB by session user_id. The POST value
 *   is never used as the authoritative save target.
 *
 *   Photo upload path uses the DB-resolved profile ID from getProfile(),
 *   not the raw POST value, so the upload location cannot be manipulated
 *   by a forged profile_id.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Site\Helper\PostGuard;
use Wendfold\Component\Wendfold\Site\Helper\ProfileMotorcycleInput;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Input\Input;

/**
 * Profile edit form controller.
 *
 * @since  1.0.0
 */
class ProfileeditController extends BaseController
{
	/**
	 * Max photo upload size in bytes (2 MB).
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	private const MAX_PHOTO_BYTES = 2097152;

	/**
	 * Allowed MIME types for photo upload.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

	/**
	 * Allowed file extensions for photo upload.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/**
	 * Handle the profile edit form submission.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function save(): void
	{
		$app = Factory::getApplication();

		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$user = $app->getIdentity();

		if (!$user || $user->guest)
		{
			$app->redirect(RouteHelper::login(RouteHelper::profileEditInternal()));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\ProfileeditModel $model */
		$model = $this->getModel('Profileedit', 'Site');

		// Resolve the true profile ID from the DB before handling the upload,
		// so the upload path cannot be controlled via a forged POST value.
		$profile = $model->getProfile();

		if (!$profile)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'), 'warning');
			$app->redirect(RouteHelper::dashboard());

			return;
		}

		$resolvedProfileId = (int) $profile->id;
		$post              = $app->getInput()->post;

		$optOutField = RenewalReminderOptOut::FORM_FIELD;
		$data = [
			'profile_id'         => $resolvedProfileId,
			'shirt_size'         => $post->getString('shirt_size', ''),
			'emergency_name'     => $post->getString('emergency_name', ''),
			'emergency_phone'    => $post->getString('emergency_phone', ''),
			'emergency_relation' => $post->getString('emergency_relation', ''),
			'motorcycles'        => $this->postedMotorcycles($post),
			'photo'              => '',
			$optOutField         => $post->getInt($optOutField, 0) === 1 ? 1 : 0,
		];

		$validSizes = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'];

		if (!\in_array($data['shirt_size'], $validSizes, true))
		{
			$data['shirt_size'] = '';
		}

		// Motorcycle validation must pass before any photo filesystem write.
		$motorcycleError = $this->motorcycleValidationError(
			$model->ownedMotorcycleStore($resolvedProfileId),
			$data['motorcycles']
		);

		if ($motorcycleError !== null)
		{
			$app->enqueueMessage($this->saveErrorMessage($motorcycleError), 'error');
			$this->rememberProfileFormData($data);
		}
		else
		{
			$uploadedPhoto = $this->handlePhotoUpload($resolvedProfileId);

			if ($uploadedPhoto === false)
			{
				// Validation error already queued by handlePhotoUpload().
				$this->rememberProfileFormData($data);
			}
			else
			{
				$data['photo'] = $uploadedPhoto !== null ? $uploadedPhoto : '';

				if ($model->save($data))
				{
					try
					{
						/** @var UserFactoryInterface $users */
						$users = Factory::getContainer()->get(UserFactoryInterface::class);
						$account = $users->loadUserById((int) $user->id);
						RenewalReminderOptOut::setOptedOut(
							$account,
							(int) $data[$optOutField] === 1
						);
					}
					catch (\Throwable $e)
					{
						// Profile fields saved; opt-out write is best-effort.
					}

					$this->clearProfileFormData();
					$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE_SUCCESS'), 'success');
					$app->redirect(RouteHelper::dashboard());

					return;
				}

				$app->enqueueMessage($this->saveErrorMessage((string) $model->getError()), 'error');
				$this->rememberProfileFormData($data);
			}
		}

		$app->redirect(RouteHelper::profileEdit());
	}

	/**
	 * Keep posted profile-edit data for redisplay after a validation error.
	 *
	 * @param   array  $data  Posted form data including opt-out.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function rememberProfileFormData(array $data): void
	{
		$app = Factory::getApplication();

		if ($app instanceof CMSWebApplicationInterface)
		{
			$app->setUserState(RenewalReminderOptOut::SITE_FORM_STATE, $data);
		}
	}

	/**
	 * Drop remembered profile-edit data after a successful save.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function clearProfileFormData(): void
	{
		$app = Factory::getApplication();

		if ($app instanceof CMSWebApplicationInterface)
		{
			$app->setUserState(RenewalReminderOptOut::SITE_FORM_STATE, null);
		}
	}

	/**
	 * Run the motorcycle subform plan without writing; return a language key on failure.
	 *
	 * @param   array<int, array<string, mixed>>  $storedById  Owned year/vin store
	 * @param   array                             $postedRows  Posted motorcycle rows
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private function motorcycleValidationError(array $storedById, array $postedRows): ?string
	{
		try
		{
			ProfileMotorcycleInput::plan(
				array_map('intval', array_keys($storedById)),
				$postedRows,
				null,
				$storedById
			);
		}
		catch (\InvalidArgumentException $error)
		{
			$key = $error->getMessage();

			if ($key === ProfileMotorcycleInput::MSG_YEAR || $key === ProfileMotorcycleInput::MSG_VIN)
			{
				return $key;
			}

			return 'COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR';
		}

		return null;
	}

	/**
	 * Translate a model error key into the flash message shown to the member.
	 *
	 * @param   string  $error  Language key from the model, or empty.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function saveErrorMessage(string $error): string
	{
		if ($error === ProfileMotorcycleInput::MSG_YEAR)
		{
			return Text::sprintf(
				ProfileMotorcycleInput::MSG_YEAR,
				ProfileMotorcycleInput::YEAR_MIN,
				ProfileMotorcycleInput::yearMax()
			);
		}

		if ($error === ProfileMotorcycleInput::MSG_VIN)
		{
			return Text::_(ProfileMotorcycleInput::MSG_VIN);
		}

		return Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR');
	}

	/**
	 * Handle the photo_upload file input.
	 *
	 * Validates type and size, generates a safe filename, moves the file
	 * to the component upload directory.
	 *
	 * @param   int  $profileId  DB-resolved profile ID (not from POST)
	 *
	 * @return  string|null|false
	 *   - string  : relative path to the new photo (e.g. "images/wendfold/profiles/42.jpg")
	 *   - null    : no file was submitted (no-op, keep existing photo)
	 *   - false   : validation failed (error already queued)
	 *
	 * @since   1.0.0
	 */
	private function handlePhotoUpload(int $profileId): string|null|false
	{
		$app  = Factory::getApplication();
		$file = $app->getInput()->files->get('photo_upload');

		if (empty($file) || empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE)
		{
			return null;
		}

		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR'), 'error');

			return false;
		}

		if ($file['size'] > self::MAX_PHOTO_BYTES)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_TOO_LARGE'), 'error');

			return false;
		}

		$originalName = strtolower(basename($file['name']));
		$ext          = pathinfo($originalName, PATHINFO_EXTENSION);

		if (!\in_array($ext, self::ALLOWED_EXT, true))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_INVALID'), 'error');

			return false;
		}

		// Finfo is the reliable MIME check — do not trust the browser MIME type.
		if (\function_exists('finfo_open'))
		{
			$finfo    = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $file['tmp_name']);

			if (!\in_array($mimeType, self::ALLOWED_MIME, true))
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_INVALID'), 'error');

				return false;
			}
		}

		// Ensure the file is a real image and not just a renamed payload.
		if (@getimagesize($file['tmp_name']) === false)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_INVALID'), 'error');

			return false;
		}

		// Store as: images/wendfold/profiles/{profileId}.{ext}
		$relDir   = 'images/wendfold/profiles';
		$absDir   = JPATH_ROOT . '/' . $relDir;
		$fileName = $profileId . '.' . $ext;
		$absPath  = $absDir . '/' . $fileName;
		$relPath  = $relDir . '/' . $fileName;

		if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR'), 'error');

			return false;
		}

		foreach (glob($absDir . '/' . $profileId . '.*') as $old)
		{
			if ($old !== $absPath)
			{
				@unlink($old);
			}
		}

		if (!move_uploaded_file($file['tmp_name'], $absPath))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR'), 'error');

			return false;
		}

		return $relPath;
	}

	/**
	 * Collect motorcycle subform rows from POST.
	 *
	 * The Remove checkbox is preserved so the plan deletes only when it is
	 * posted. A legacy single-row moto_* post is mapped into one subform row
	 * when the array field is absent.
	 *
	 * @param   Input  $post  Request POST bag
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function postedMotorcycles(Input $post): array
	{
		$raw = $post->get('motorcycles', null, 'array');

		if (!\is_array($raw))
		{
			return [
				[
					'id'       => $post->getInt('moto_id', 0),
					'year'     => $post->getString('moto_year', ''),
					'make'     => $post->getString('moto_make', ''),
					'model'    => $post->getString('moto_model', ''),
					'color'    => $post->getString('moto_color', ''),
					'nickname' => $post->getString('moto_nickname', ''),
					'vin'      => $post->getString('moto_vin', ''),
					'remove'   => false,
				],
			];
		}

		$rows = [];

		foreach ($raw as $row)
		{
			if (!\is_array($row))
			{
				continue;
			}

			$rows[] = [
				'id'       => (int) ($row['id'] ?? 0),
				'year'     => (string) ($row['year'] ?? ''),
				'make'     => (string) ($row['make'] ?? ''),
				'model'    => (string) ($row['model'] ?? ''),
				'color'    => (string) ($row['color'] ?? ''),
				'nickname' => (string) ($row['nickname'] ?? ''),
				'vin'      => (string) ($row['vin'] ?? ''),
				'remove'   => !empty($row['remove']),
			];
		}

		return $rows;
	}
}
