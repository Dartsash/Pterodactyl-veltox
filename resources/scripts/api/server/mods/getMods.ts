import http from '@/api/http';

export interface ModDetection {
    loader: string | null;
    gameVersion: string | null;
    source: string | null;
}

export interface InstalledMod {
    // Real name on disk, including the .disabled suffix when switched off.
    name: string;
    // Name without the suffix, which is what the user should see.
    displayName: string;
    size: number;
    modified: string | null;
    enabled: boolean;
}

export interface ModsOverview {
    detected: ModDetection;
    loaders: string[];
    sorts: string[];
    gameVersions: string[];
    directory: string;
    allowClientMods: boolean;
    installed: InstalledMod[];
}

export interface ModResult {
    slug: string;
    title: string;
    description: string;
    author: string;
    downloads: number;
    follows: number;
    icon: string | null;
    categories: string[];
    loaders: string[];
    gameVersions: string[];
    clientSide: string;
    serverSide: string;
    url: string;
}

export interface ModVersion {
    id: string;
    name: string;
    number: string;
    type: string;
    gameVersions: string[];
    loaders: string[];
    published: string | null;
    downloads: number;
    filename: string;
    size: number;
    requiredDependencies: number;
}

export interface ModFilters {
    loader?: string | null;
    gameVersion?: string | null;
}

export interface InstallResult {
    installed: string[];
    unresolvedDependencies: string[];
    files: InstalledMod[];
}

const toInstalled = (data: any): InstalledMod => ({
    name: data.name,
    displayName: data.display_name,
    size: Number(data.size || 0),
    modified: data.modified ?? null,
    enabled: Boolean(data.enabled),
});

const toOverview = (data: any): ModsOverview => ({
    detected: {
        loader: data.detected?.loader ?? null,
        gameVersion: data.detected?.game_version ?? null,
        source: data.detected?.source ?? null,
    },
    loaders: data.loaders || [],
    sorts: data.sorts || [],
    gameVersions: data.game_versions || [],
    directory: data.directory || '/mods',
    allowClientMods: Boolean(data.allow_client_mods),
    installed: (data.installed || []).map(toInstalled),
});

const toResult = (data: any): ModResult => ({
    slug: data.slug,
    title: data.title,
    description: data.description || '',
    author: data.author || '',
    downloads: Number(data.downloads || 0),
    follows: Number(data.follows || 0),
    icon: data.icon ?? null,
    categories: data.categories || [],
    loaders: data.loaders || [],
    gameVersions: data.game_versions || [],
    clientSide: data.client_side || 'unknown',
    serverSide: data.server_side || 'unknown',
    url: data.url,
});

const toVersion = (data: any): ModVersion => ({
    id: data.id,
    name: data.name || '',
    number: data.number || '',
    type: data.type || 'release',
    gameVersions: data.game_versions || [],
    loaders: data.loaders || [],
    published: data.published ?? null,
    downloads: Number(data.downloads || 0),
    filename: data.filename,
    size: Number(data.size || 0),
    requiredDependencies: Number(data.required_dependencies || 0),
});

export default async (uuid: string): Promise<ModsOverview> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/mods`);

    return toOverview(data.data);
};

export const searchMods = async (
    uuid: string,
    filters: ModFilters & { query?: string; sort?: string }
): Promise<ModResult[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/mods/search`, {
        params: {
            q: filters.query || undefined,
            loader: filters.loader || undefined,
            game_version: filters.gameVersion || undefined,
            sort: filters.sort || undefined,
        },
    });

    return (data.data || []).map(toResult);
};

export const getModVersions = async (uuid: string, slug: string, filters: ModFilters): Promise<ModVersion[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/mods/${slug}/versions`, {
        params: {
            loader: filters.loader || undefined,
            game_version: filters.gameVersion || undefined,
        },
    });

    return (data.data || []).map(toVersion);
};

export const installMod = async (
    uuid: string,
    slug: string,
    payload: ModFilters & { version?: string | null; dependencies?: boolean }
): Promise<InstallResult> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/mods/${slug}/install`, {
        version: payload.version || undefined,
        loader: payload.loader || undefined,
        game_version: payload.gameVersion || undefined,
        dependencies: payload.dependencies ?? false,
    });

    return {
        installed: data.data.installed || [],
        unresolvedDependencies: data.data.unresolved_dependencies || [],
        files: (data.data.files || []).map(toInstalled),
    };
};

export const setModState = async (uuid: string, file: string, enabled: boolean): Promise<InstalledMod[]> => {
    const { data } = await http.patch(`/api/client/servers/${uuid}/mods/state`, { file, enabled });

    return (data.data.installed || []).map(toInstalled);
};

export const deleteMod = async (uuid: string, file: string): Promise<void> => {
    // Sent as a POST because some proxies strip bodies from DELETE requests.
    await http.post(`/api/client/servers/${uuid}/mods/delete`, { file });
};
