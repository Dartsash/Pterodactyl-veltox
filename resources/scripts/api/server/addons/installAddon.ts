import http from '@/api/http';

export default (uuid: string, addon: string, version?: string | null): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/addons/${addon}/install`, version ? { version } : {})
            .then(() => resolve())
            .catch(reject);
    });
};
