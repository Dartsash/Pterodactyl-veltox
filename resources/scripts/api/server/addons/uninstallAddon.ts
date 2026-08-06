import http from '@/api/http';

export default (uuid: string, addon: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${uuid}/addons/${addon}`)
            .then(() => resolve())
            .catch(reject);
    });
};
