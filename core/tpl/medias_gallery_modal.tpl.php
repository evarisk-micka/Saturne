<?php
global $db;

require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmdirectory.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

$ecmdir           = new EcmDirectory($db);
$ecmfile          = new EcmFiles($db);

if ( ! $error && $subaction == "uploadPhoto" && ! empty($conf->global->MAIN_UPLOAD_DOC)) {

	// Define relativepath and upload_dir
	$relativepath                                             = $module . '/medias';
	$upload_dir                                               = $conf->ecm->dir_output . '/' . $relativepath;
	if (is_array($_FILES['userfile']['tmp_name'])) $userfiles = $_FILES['userfile']['tmp_name'];
	else $userfiles                                           = array($_FILES['userfile']['tmp_name']);

	foreach ($userfiles as $key => $userfile) {
		$error = 0;
		if (empty($_FILES['userfile']['tmp_name'][$key])) {
			$error++;
			if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
				setEventMessages($langs->transnoentitiesnoconv('ErrorThisFileSizeTooLarge', $_FILES['userfile']['name'][$key]), null, 'errors');
				$submit_file_error_text = array('message' => $langs->transnoentitiesnoconv('ErrorThisFileSizeTooLarge', $_FILES['userfile']['name'][$key]), 'code' => '1337');
			} else {
				setEventMessages($langs->transnoentitiesnoconv("ErrorThisFileSizeTooLarge", $_FILES['userfile']['name'][$key], $langs->transnoentitiesnoconv("File")), null, 'errors');
				$submit_file_error_text = array('message' => $langs->transnoentitiesnoconv('ErrorThisFileSizeTooLarge', $_FILES['userfile']['name'][$key]), 'code' => '1337');
			}
		}

		if ( ! $error) {
			$generatethumbs = 1;
			$res = dol_add_file_process($upload_dir, 0, 1, 'userfile', '', null, '', $generatethumbs);
			if ($res > 0) {
				$imgThumbLarge  = vignette($upload_dir . '/' . $_FILES['userfile']['name'][$key], $conf->global->SATURNE_MEDIA_MAX_WIDTH_LARGE, $conf->global->SATURNE_MEDIA_MAX_HEIGHT_LARGE, '_large');
				$imgThumbMedium = vignette($upload_dir . '/' . $_FILES['userfile']['name'][$key], $conf->global->SATURNE_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->SATURNE_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
				$result = $ecmdir->changeNbOfFiles('+');
			} else {
				setEventMessages($langs->transnoentitiesnoconv("ErrorThisFileExists", $_FILES['userfile']['name'][$key], $langs->transnoentitiesnoconv("File")), null, 'errors');
				$submit_file_error_text = array('message' => $langs->transnoentities('ErrorThisFileExists', $_FILES['userfile']['name'][$key]), 'code' => '1337');
			}
		}
	}
}

if ( ! $error && $subaction == "addFiles") {
	$data = json_decode(file_get_contents('php://input'), true);

	$filenames     = $data['filenames'];
	$objectId      = $data['objectId'];
	$objectType    = $data['objectType'];
	$objectSubtype = $data['objectSubtype'];
	$objectSubdir  = $data['objectSubdir'];

	$object = new $objectType($db);
	$object->fetch($objectId);

	$modObjectName = strtoupper($module) . '_' . strtoupper($objectType) . '_ADDON';
	$modObject     = new $conf->global->$modObjectName($db);

	if (dol_strlen($object->ref) > 0) {
		$pathToObjectPhoto = $conf->$module->multidir_output[$conf->entity] . '/'. $objectType .'/' . $object->ref . '/' . $objectSubdir;
	} else {
		$pathToObjectPhoto = $conf->$module->multidir_output[$conf->entity] . '/'. $objectType .'/tmp/' . $modObject->prefix . '0/' . $objectSubdir ;
	}

	if (preg_match('/vVv/', $filenames)) {
		$filenames = preg_split('/vVv/', $filenames);
		array_pop($filenames);
	} else {
		$filenames = array($filenames);
	}

	if ( ! (empty($filenames))) {
		if ( ! is_dir($conf->$module->multidir_output[$conf->entity] . '/'. $objectType . '/tmp/')) {
			dol_mkdir($conf->$module->multidir_output[$conf->entity] . '/'. $objectType . '/tmp/');
		}

		if ( ! is_dir($conf->$module->multidir_output[$conf->entity] . '/'. $objectType . '/' . (dol_strlen($object->ref) > 0 ? $object->ref : 'tmp/' . $modObject->prefix . '0/') )) {
			dol_mkdir($conf->$module->multidir_output[$conf->entity] . '/'. $objectType . '/' . (dol_strlen($object->ref) > 0 ? $object->ref : 'tmp/' . $modObject->prefix . '0/'));
		}

		foreach ($filenames as $filename) {
			$entity = ($conf->entity > 1) ? '/' . $conf->entity : '';

			if (is_file($conf->ecm->multidir_output[$conf->entity] . '/'. $module .'/medias/' . $filename)) {
				$pathToECMPhoto = $conf->ecm->multidir_output[$conf->entity] . '/'. $module .'/medias/' . $filename;

				if ( ! is_dir($pathToObjectPhoto)) {
					mkdir($pathToObjectPhoto, 0777, true);
				}

				if (file_exists($pathToECMPhoto)) {
					copy($pathToECMPhoto, $pathToObjectPhoto . '/' . $filename);
					$ecmfile->fetch(0,'',($conf->entity > 1 ? $conf->entity . '/' : ''). 'ecm/'. $module .'/medias/' . $filename);
					$date      = dol_print_date(dol_now(),'dayxcard');
					$extension = pathinfo($filename, PATHINFO_EXTENSION);
					$newFilename = $conf->entity . '_' . $ecmfile->id . '_' . (dol_strlen($object->ref) > 0 ? $object->ref : $modObject->getNextValue($object)) . '_' . $date . '.' . $extension;
					rename($pathToObjectPhoto . '/' . $filename, $pathToObjectPhoto . '/' . $newFilename);

					global $maxwidthmini, $maxheightmini, $maxwidthsmall,$maxheightsmall ;
					$destfull = $pathToObjectPhoto . '/' . $newFilename;

					// Create thumbs
					$imgThumbSmall  = vignette($destfull, $maxwidthsmall, $maxheightsmall);
					$imgThumbMini   = vignette($destfull, $maxwidthmini, $maxheightmini, '_mini');
					$imgThumbLarge  = vignette($destfull, $conf->global->SATURNE_MEDIA_MAX_WIDTH_LARGE, $conf->global->SATURNE_MEDIA_MAX_HEIGHT_LARGE, '_large');
					$imgThumbMedium = vignette($destfull, $conf->global->SATURNE_MEDIA_MAX_WIDTH_MEDIUM, $conf->global->SATURNE_MEDIA_MAX_HEIGHT_MEDIUM, '_medium');
					// Create mini thumbs for image (Ratio is near 16/9)
				}
			}
		}
	}
}

if ( ! $error && $subaction == "unlinkFile") {
	$data = json_decode(file_get_contents('php://input'), true);

	$filepath = $data['filepath'];

	if (is_file($filepath)) {
		unlink($filepath);
	}
}

if ( ! $error && $subaction == "pagination") {
	$data = json_decode(file_get_contents('php://input'), true);

	$offset       = $data['offset'];
	$pagesCounter = $data['pagesCounter'];

	$page_array = saturne_load_pagination($pagesCounter, $page_array, $offset);
}

if (is_array($submit_file_error_text)) {
	print '<input class="error-medias" value="'. htmlspecialchars(json_encode($submit_file_error_text)) .'">';
}
?>
<!-- START MEDIA GALLERY MODAL -->
<div class="wpeo-modal modal-photo" id="media_gallery" data-id="<?php echo $object->id ?: 0?>">
	<div class="modal-container wpeo-modal-event">
		<!-- Modal-Header -->
		<div class="modal-header">
			<h2 class="modal-title"><?php echo $langs->trans('ModalAddPhoto')?></h2>
			<div class="modal-close"><i class="fas fa-2x fa-times"></i></div>
		</div>
		<!-- Modal-Content -->
		<div class="modal-content" id="#modalMediaGalleryContent">
			<div class="messageSuccessSendPhoto notice hidden">
				<div class="wpeo-notice notice-success send-photo-success-notice">
					<div class="notice-content">
						<div class="notice-title"><?php echo $langs->trans('PhotoWellSent') ?></div>
					</div>
					<div class="notice-close"><i class="fas fa-times"></i></div>
				</div>
			</div>
			<div class="messageErrorSendPhoto notice hidden">
				<div class="wpeo-notice notice-error send-photo-error-notice">
					<div class="notice-content">
						<div class="notice-title"><?php echo $langs->trans('PhotoNotSent') ?></div>
						<div class="notice-subtitle"></div>
					</div>
					<div class="notice-close"><i class="fas fa-times"></i></div>
				</div>
			</div>
			<div class="wpeo-gridlayout grid-2">
				<div class="modal-add-media">
					<?php
					print '<input type="hidden" name="token" value="'.newToken().'">';
					if (( ! empty($conf->use_javascript_ajax) && empty($conf->global->MAIN_ECM_DISABLE_JS)) || ! empty($section)) {
						$sectiondir = GETPOST('file', 'alpha') ? GETPOST('file', 'alpha') : GETPOST('section_dir', 'alpha');
						print '<!-- Start form to attach new file in '. $module .'_photo_view.tpl.php sectionid=' . $section . ' sectiondir=' . $sectiondir . ' -->' . "\n";
						include_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
						print '<strong>' . $langs->trans('AddFile') . '</strong>'
						?>
						<input type="file" id="add_media_to_gallery" class="flat minwidth400 maxwidth200onsmartphone" name="userfile[]" multiple accept>
					<?php } else print '&nbsp;'; ?>
					<div class="underbanner clearboth"></div>
				</div>
				<div class="form-element">
					<span class="form-label"><strong><?php print $langs->trans('SearchFile') ?></strong></span>
					<div class="form-field-container">
						<div class="wpeo-autocomplete">
							<label class="autocomplete-label" for="media-gallery-search">
								<i class="autocomplete-icon-before fas fa-search"></i>
								<input id="search_in_gallery" placeholder="<?php echo $langs->trans('Search') . '...' ?>" class="autocomplete-search-input" type="text" />
							</label>
						</div>
					</div>
				</div>
			</div>
			<div id="progressBarContainer" style="display:none">
				<div id="progressBar"></div>
			</div>
			<div class="ecm-photo-list-content">
				<div class="wpeo-gridlayout grid-5 grid-gap-3 grid-margin-2 ecm-photo-list ecm-photo-list">
					<?php
					$relativepath = $module . '/medias/thumbs';
					print saturne_show_medias($module, 'ecm', $conf->ecm->multidir_output[$conf->entity] . '/'. $module .'/medias', ($conf->browser->layout == 'phone' ? 'mini' : 'small'), 80, 80, (!empty($offset) ? $offset : 1));
					?>
				</div>
			</div>
		</div>
		<!-- Modal-Footer -->
		<div class="modal-footer">
			<?php
			$filearray                    = dol_dir_list($conf->ecm->multidir_output[$conf->entity] . '/'. $module .'/medias/', "files", 0, '', '(\.meta|_preview.*\.png)$', 'date', SORT_DESC);
			$moduleImageNumberPerPageConf = strtoupper($module) . '_DISPLAY_NUMBER_MEDIA_GALLERY';
			$allMediasNumber              = count($filearray);
			$pagesCounter                 = $conf->global->$moduleImageNumberPerPageConf ? ceil($allMediasNumber/($conf->global->$moduleImageNumberPerPageConf ?: 1)) : 1;
			$page_array                   = saturne_load_pagination($pagesCounter, $page_array, $offset);

			print saturne_show_pagination($pagesCounter, $page_array, $offset);
			?>
			<div class="save-photo wpeo-button button-blue button-disable" value="">
				<input class="from-type" value="" type="hidden"/>
				<input class="from-subtype" value="" type="hidden"/>
				<input class="from-id" value="" type="hidden"/>
				<input class="from-subdir" value="" type="hidden"/>
				<span><?php echo $langs->trans('Add'); ?></span>
			</div>
		</div>
	</div>
</div>
<!-- END MEDIA GALLERY MODAL -->
