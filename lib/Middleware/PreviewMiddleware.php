<?php

namespace OCA\PreviewGenerator\Middleware;

use OC\Core\Controller\PreviewController;
use OC\OCS\Exception;
use OCP\IDBConnection;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\Files\NotFoundException;
use OCP\AppFramework\Http\DataResponse;

class PreviewMiddleware extends Middleware {

    /** @var IDBConnection */
    private $connection;

    public function __construct(IDBConnection $connection) {
        $this->connection = $connection;
    }

    public function afterException($controller, $methodName, \Exception $exception) {
        if ($exception instanceof NotFoundException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }

    }

    public function isPreviewReady($fileId) : bool
    {
        $qb = $this->connection->getQueryBuilder();
        $qb->select('id')
            ->from('preview_generation')
            ->where(
                $qb->expr()->eq('file_id', $qb->createNamedParameter($fileId))
            )->setMaxResults(1);
        $cursor = $qb->execute();
        $inTable = $cursor->fetch() !== false;
        $cursor->closeCursor();

        // If $fileId in queue to rendering, it is not ready for preview
        return !$inTable;
    }

    public function beforeController($controller, $methodName)
    {
        if ($controller instanceof PreviewController) {
            $config = \OC::$server->getConfig();

            if (!$config->getSystemValue('enable_generated_previews_only', true)) {
                // Just return in case of disabled plugin setting. So previewManager can generate preview on the fly.
                return;
            }

            $root = \OC::$server->getRootFolder();
            $userId = \OC::$server->getUserSession()->getUser()->getUID();
            $request = \OC::$server->getRequest();
            $userFolder = $root->getUserFolder($userId);
            if (!$this->TryGetFileIdFromRequest($methodName, $userFolder, $request, $fileId))
            {
                throw new NotFoundException();
            }

            // Don't call deeper funtion because this $fileId in order to be generated
            if (!$this->isPreviewReady($fileId)) {
                throw new NotFoundException();
            }
        }
    }

    /**
     * @param string $methodName
     * @param \OCP\Files\Folder $userFolder
     * @param \OCP\IRequest $request
     * @param $fileId
     */
    public function TryGetFileIdFromRequest(string $methodName, \OCP\Files\Folder $userFolder, \OCP\IRequest $request, &$fileId): bool
    {
        try{
            if ($methodName === 'getPreview') {
                $fileId = $userFolder->get($request->getParam('file'))->getId();
            } elseif ($methodName === 'getPreviewByFileId') {
                $fileId = $request->getParam('fileId');
            }
            return true;
        } catch (Exception $ex)
        {
            return false;
        }
    }
}