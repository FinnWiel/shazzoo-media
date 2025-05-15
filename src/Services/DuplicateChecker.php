<?php
namespace FinnWiel\ShazzooMedia\Services;

use FinnWiel\ShazzooMedia\Models\MediaExtended;

class DuplicateChecker
{
    /**
     * Check if a file is a duplicate based on its hash.
     * 
     * @param string $hash The hash of the file.
     * @param mixed $tenantValue The tenant value to scope the query. If null, it will use the default resolver.
     * @return bool True if the file is a duplicate, false otherwise.
     */
    public static function isDuplicate(string $hash, $tenantValue = null): bool
    {
        $config = config('shazzoo_media.tenant_scoping');
        $field = $config['field'] ?? 'tenant_id';
        $resolver = $config['resolver'] ?? null;

        if ($tenantValue === null && is_callable($resolver)) {
            $tenantValue = $resolver();
        }

        $query = MediaExtended::where('file_hash', $hash);

        if (($config['enabled'] ?? false) && $tenantValue !== null) {
            $query->where($field, $tenantValue);
        }

        return $query->exists();
    }
}
