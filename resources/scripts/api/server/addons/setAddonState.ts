import http from '@/api/http';

export default (uuid: string, addon: string, enabled: boolean): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.patch(`/api/client/servers/${uuid}/addons/${addon}/state`, { enabled })
            .then(() => resolve())
            .catch(reject);
    });
};
