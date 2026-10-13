<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AdminShell;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\NewsMailFeedValidator;
use ChurchCMS\Core\NewsMailSyndicationRenderer;
use ChurchCMS\Core\RamblerFeedValidator;
use ChurchCMS\Core\RamblerSyndicationRenderer;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Smi2FeedValidator;
use ChurchCMS\Core\Smi2SyndicationRenderer;
use ChurchCMS\Core\SyndicationExportLogRepository;
use ChurchCMS\Core\SyndicationFeed;
use ChurchCMS\Core\SyndicationRegistry;
use ChurchCMS\Core\SyndicationRenderer;

final class SyndicationAdminController
{
    public function index(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');
        $target = trim((string) $request->get('target'));
        if (!in_array($target, ['', 'rss', 'rambler', 'smi2', 'news_mail'], true)) {
            $target = '';
        }
        $entries = SyndicationExportLogRepository::fromDefaultConnection()
            ->recent(200, $target === '' ? null : $target);
        AdminShell::page($request, 'admin.syndication_exports', [
            'title' => 'Журнал экспортов',
            'entries' => $entries,
            'target' => $target,
        ], 'publications');
    }

    public function ramblerValidator(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');
        $result = $this->validateTarget(
            'rambler',
            new RamblerSyndicationRenderer(),
            static fn(string $xml, string $contentType): array =>
                (new RamblerFeedValidator())->validate($xml, $contentType),
        );
        AdminShell::page($request, 'admin.rambler_validator', [
            'title' => 'Проверка Rambler-фида',
            ...$result,
        ], 'publications');
    }

    public function smi2Validator(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');
        $result = $this->validateTarget(
            'smi2',
            new Smi2SyndicationRenderer(),
            static fn(string $xml, string $contentType): array =>
                (new Smi2FeedValidator())->validate($xml, $contentType),
        );
        $partnerApproved = Config::get(
            'syndication.targets.smi2.partner_approved',
            false,
        ) === true;
        $technicalReady = $result['configured']
            && $result['itemCount'] > 0
            && $result['errorCount'] === 0;
        AdminShell::page($request, 'admin.smi2_validator', [
            'title' => 'Проверка фида СМИ2',
            ...$result,
            'partnerApproved' => $partnerApproved,
            'technicalReady' => $technicalReady,
            'diagnosticCode' => $technicalReady && !$partnerApproved
                ? 'partner_access_required'
                : null,
        ], 'publications');
    }

    public function newsMailValidator(Request $request): never
    {
        AdminAuthorization::requirePermission($request, 'publications.read');
        $result = $this->validateTarget(
            'news_mail',
            new NewsMailSyndicationRenderer(),
            static fn(string $xml, string $contentType): array =>
                (new NewsMailFeedValidator())->validate($xml, $contentType),
        );
        $partnerApproved = Config::get(
            'syndication.targets.news_mail.partner_approved',
            false,
        ) === true;
        $contractConfirmed = Config::get(
            'syndication.targets.news_mail.delivery_contract_confirmed',
            false,
        ) === true;
        $technicalReady = $result['configured']
            && $result['itemCount'] > 0
            && $result['errorCount'] === 0;
        $diagnosticCode = null;
        if ($technicalReady && (!$partnerApproved || !$contractConfirmed)) {
            $diagnosticCode = 'partner_access_required';
        }
        AdminShell::page($request, 'admin.news_mail_validator', [
            'title' => 'Проверка контура Новости Mail',
            ...$result,
            'partnerApproved' => $partnerApproved,
            'contractConfirmed' => $contractConfirmed,
            'technicalReady' => $technicalReady,
            'diagnosticCode' => $diagnosticCode,
        ], 'publications');
    }

    /**
     * @param callable(string,string):array $validator
     * @return array{configured:bool,issues:array,itemCount:int,errorCount:int,warningCount:int}
     */
    private function validateTarget(
        string $target,
        SyndicationRenderer $renderer,
        callable $validator,
    ): array {
        $siteUrl = rtrim((string) Config::get('syndication.site_url', ''), '/');
        $configured = $siteUrl !== ''
            && filter_var($siteUrl, FILTER_VALIDATE_URL) !== false
            && Config::get("syndication.targets.{$target}.enabled", false) === true;
        $issues = [];
        $itemCount = 0;
        if ($configured) {
            $entries = SyndicationRegistry::entriesFor($target, 100);
            $itemCount = count($entries);
            $feed = new SyndicationFeed(
                title: (string) Config::get('syndication.channel_title', 'ChurchCMS'),
                siteUrl: $siteUrl . '/',
                description: (string) Config::get('syndication.channel_description', 'ChurchCMS feed'),
                entries: $entries,
            );
            $issues = $validator($renderer->render($feed), $renderer->contentType());
        }
        $errorCount = count(array_filter(
            $issues,
            static fn(array $issue): bool => ($issue['level'] ?? '') === 'error',
        ));
        return [
            'configured' => $configured,
            'issues' => $issues,
            'itemCount' => $itemCount,
            'errorCount' => $errorCount,
            'warningCount' => count($issues) - $errorCount,
        ];
    }
}
