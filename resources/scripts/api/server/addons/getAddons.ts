import http from '@/api/http';

export interface Addon {
    id: string;
    name: string;
    author: string;
    category: 'Plugin' | 'Mod' | 'Datapack';
    description: string;
    version: string;
    downloads: string;
    rating: number;
    installed: boolean;
    installedVersion: string | null;
    enabled: boolean;
    /** True when the addon publishes a list of versions we can offer. */
    hasVersions: boolean;
}

export default (uuid: string): Promise<Addon[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/addons`)
            .then(({ data }) =>
                resolve(
                    (data.data || []).map(
                        (d: any): Addon => ({
                            id: d.id,
                            name: d.name,
                            author: d.author,
                            category: d.category,
                            description: d.description,
                            version: d.version,
                            downloads: d.downloads,
                            rating: d.rating,
                            installed: Boolean(d.installed),
                            installedVersion: d.installed_version ?? null,
                            enabled: d.enabled !== false,
                            hasVersions: Boolean(d.has_versions),
                        })
                    )
                )
            )
            .catch(reject);
    });
};
