<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers;

use PhpMyAdmin\Core;
use PhpMyAdmin\Exceptions\ExportException;
use PhpMyAdmin\Export\Export;
use PhpMyAdmin\Html\MySQLDocumentation;
use PhpMyAdmin\Http\Factory\ResponseFactory;
use PhpMyAdmin\Http\Response;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Identifiers\DatabaseName;
use PhpMyAdmin\Message;
use PhpMyAdmin\ResponseRenderer;

use function __;
use function is_string;
use function mb_strlen;

/**
 * Schema export handler
 */
final class SchemaExportController implements InvocableController
{
    public function __construct(
        private readonly Export $export,
        private readonly ResponseRenderer $response,
        private readonly ResponseFactory $responseFactory,
    ) {
    }

    public function __invoke(ServerRequest $request): Response|null
    {
        $db = DatabaseName::tryFrom($request->getParsedBodyParam('db'));
        /** @var mixed $exportType */
        $exportType = $request->getParsedBodyParam('export_type');
        if ($db === null || ! is_string($exportType) || $exportType === '') {
            $errorMessage = __('Missing parameter:') . ($db === null ? ' db' : ' export_type')
                . MySQLDocumentation::showDocumentation('faq', 'faqmissingparameters', true)
                . '[br]';
            $this->response->setRequestStatus(false);
            $this->response->addHTML(Message::error($errorMessage)->getDisplay());

            return null;
        }

        /**
         * Include the appropriate Schema Class depending on $exportType, default is PDF.
         */
        try {
            $exportInfo = $this->export->getExportSchemaInfo($db, $exportType);
        } catch (ExportException $exception) {
            $this->response->setRequestStatus(false);
            $this->response->addHTML(Message::error($exception->getMessage())->getDisplay());

            return null;
        }

        $response = $this->responseFactory->createResponse();
        Core::downloadHeader(
            $exportInfo['fileName'],
            $exportInfo['mediaType'],
            mb_strlen($exportInfo['fileData'], '8bit'),
        );

        return $response->write($exportInfo['fileData']);
    }
}
