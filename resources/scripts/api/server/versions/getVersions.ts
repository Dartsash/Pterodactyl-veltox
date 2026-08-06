import http from '@/api/http';

export interface ServerCore {
    key: string;
    name: string;
    category: string;
    categoryLabel: string;
    description: string;
    // Forge ships an installer jar instead of a ready to run server.
    installer: boolean;
    hasBuilds: boolean;
}

export interface VersionManagerData {
    cores: ServerCore[];
    categories: Record<string, string>;
    // The jar the server currently boots, taken from SERVER_JARFILE.
    jarFile: string;
}

export interface InstallResult {
    label: string;
    filename: string;
    installer: boolean;
    // How many top level files and folders were removed before the download.
    wiped: number;
    // The URL the jar is being pulled from, shown so a failed download can be
    // checked by hand.
    url: string;
}

export default async (uuid: string): Promise<VersionManagerData> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions`);

    return {
        cores: (data.data.cores || []).map((core: any) => ({
            key: core.key,
            name: core.name,
            category: core.category,
            categoryLabel: core.category_label,
            description: core.description,
            installer: Boolean(core.installer),
            hasBuilds: Boolean(core.has_builds),
        })),
        categories: data.data.categories || {},
        jarFile: data.data.jar_file,
    };
};

export const getCoreVersions = async (uuid: string, core: string): Promise<string[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions/${core}`);

    // Some upstream APIs answer with numbers, always work with strings here.
    return (data.data.versions || []).map((version: unknown) => String(version));
};

export const getCoreBuilds = async (uuid: string, core: string, version: string): Promise<string[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions/${core}/${encodeURIComponent(version)}`);

    return (data.data.builds || []).map((build: unknown) => String(build));
};

export const installVersion = async (
    uuid: string,
    core: string,
    version: string,
    build: string | null,
    wipe = false,
): Promise<InstallResult> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/versions/install`, {
        core,
        version: String(version),
        build: build === null || build === '' ? null : String(build),
        wipe,
    });

    return {
        label: data.data.label,
        filename: data.data.filename,
        installer: Boolean(data.data.installer),
        wiped: Number(data.data.wiped || 0),
        url: String(data.data.url || ''),
    };
};
