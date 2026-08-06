import http from '@/api/http';

export interface AddonVersion {
    version: string;
    gameVersions: string[];
    loaders: string[];
    released: string | null;
    prerelease: boolean;
}

export interface AddonVersions {
    id: string;
    source: string;
    installedVersion: string | null;
    versions: AddonVersion[];
}

export default (uuid: string, addon: string): Promise<AddonVersions> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/addons/${addon}/versions`)
            .then(({ data }) =>
                resolve({
                    id: data.data.id,
                    source: data.data.source,
                    installedVersion: data.data.installed_version ?? null,
                    versions: (data.data.versions || []).map(
                        (v: any): AddonVersion => ({
                            version: v.version,
                            gameVersions: v.game_versions || [],
                            loaders: v.loaders || [],
                            released: v.released ?? null,
                            prerelease: Boolean(v.prerelease),
                        })
                    ),
                })
            )
            .catch(reject);
    });
};
