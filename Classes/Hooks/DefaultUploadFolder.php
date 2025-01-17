<?php
declare(strict_types=1);
namespace BeechIt\DefaultUploadFolder\Hooks;
/*
 * All code (c) Beech Applications B.V. all rights reserved
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;

/**
 * Class DefaultUploadFolder
 */
class DefaultUploadFolder
{

    /**
     * Get default upload folder
     *
     * @param array $params
     * @param BackendUserAuthentication $backendUserAuthentication
     * @return Folder|null
     */
    public function getDefaultUploadFolder($params, BackendUserAuthentication $backendUserAuthentication):?Folder
    {
        if (!($params['uploadFolder'] instanceof Folder)) {
            return null;
        }
        $rteParameters = $_GET['P'] ?? [];
        /** @var Folder $uploadFolder */
        $uploadFolder = $params['uploadFolder'];
        $table = $params['table'] ?? $rteParameters['table'];
        $field = $params['field'] ?? $rteParameters['fieldName'];
        $pid = $params['pid'] ?? $rteParameters['pid'] ?? 0;
        $pageTs = BackendUtility::getPagesTSconfig($pid);
        $userTsConfig = $backendUserAuthentication->getTSConfig();

        $subFolder = $this->getDefaultUploadFolderForTableAndField($table, $field, $pageTs, $userTsConfig);
        if (trim($subFolder) === '') {
            $subFolder = $this->getDefaultUploadFolderForTable($table, $pageTs, $userTsConfig);
        }
        if (trim($subFolder) === '') {
            $subFolder = $this->getDefaultUploadFolderForAllTables($pageTs, $userTsConfig);
        }

        // Folder by combined identifier
        if (preg_match('/[0-9]+:/', $subFolder)) {
            try {
                $uploadFolder = GeneralUtility::makeInstance(ResourceFactory::class)->getFolderObjectFromCombinedIdentifier(
                    $subFolder
                );
            } catch (FolderDoesNotExistException $e) {

                if (strpos($subFolder, ':') !== false) {
                    $parts = explode(':', $subFolder);

                    $data = [
                        'newfolder' => [
                            0 => [
                                'data' => $parts[1],
                                'target' => $parts[0].':/',
                            ]
                        ]
                    ];

                    $fileProcessor = GeneralUtility::makeInstance(ExtendedFileUtility::class);
                    $fileProcessor->setActionPermissions();
                    $fileProcessor->start($data);
                    $fileData = $fileProcessor->processData();

                    $uploadFolder = ResourceFactory::getInstance()->getFolderObjectFromCombinedIdentifier(
                        $subFolder
                    );
                }
            }
        }

        if (trim($subFolder) && $uploadFolder instanceof Folder && $uploadFolder->hasFolder($subFolder)
        ) {
            $uploadFolder = $uploadFolder->getSubfolder($subFolder);
        }

        return $uploadFolder;
    }

    protected function getDefaultUploadFolderForTableAndField(
        $table,
        $field,
        array $defaultPageTs,
        array $userTsConfig
    )
    {
        $subFolder = $defaultPageTs['default_upload_folders.'][$table.'.'][$field] ?? '';
        if (empty($subFolder)) {
            $subFolder = $userTsConfig['default_upload_folders.'][$table.'.'][$field] ?? '';
        }
        return $subFolder;
    }

    protected function getDefaultUploadFolderForTable(
        $table,
        array $defaultPageTs,
        array $userTsConfig
    )
    {
        $subFolder = $defaultPageTs['default_upload_folders.'][$table] ?? '';
        if (empty($subFolder)) {
            $subFolder = $userTsConfig['default_upload_folders.'][$table] ?? '';
        }
        return $subFolder;
    }

    protected function getDefaultUploadFolderForAllTables(
        array $defaultPageTs,
        array $userTsConfig
    )
    {
        $subFolder = $defaultPageTs['default_upload_folders.']['defaultForAllTables'] ?? '';
        if (empty($subFolder)) {
            $subFolder = $userTsConfig['default_upload_folders.']['defaultForAllTables'] ?? '';
        }
        return $subFolder;
    }
}
