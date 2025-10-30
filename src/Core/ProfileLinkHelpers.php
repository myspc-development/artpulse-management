<?php
namespace ArtPulse\Core;

use function add_query_arg;

final class ProfileLinkHelpers {
    public static function is_public(array $state): bool {
        return ($state['status'] ?? '') === 'publish'
            && ($state['visibility'] ?? '') === 'public'
            && !empty($state['public_url']);
    }

    public static function assemble_links(array $state): array {
        $isPublic = self::is_public($state);
        $publicUrl = $state['public_url'] ?? '';
        return [
            'view'    => $isPublic ? $publicUrl : '',
            'preview' => !$isPublic && $publicUrl ? add_query_arg('preview', 'true', $publicUrl) : '',
            'edit'    => $state['builder_url'] ?? '',
        ];
    }
}
