import http from '@/api/http';

export interface Announcement {
    title: string;
    message: string;
    type: 'info' | 'success' | 'warning' | 'danger';
    dismissible: boolean;
    version: string;
}

/**
 * Fetches the panel wide announcement configured under /admin/addons.
 * Resolves with null when there is nothing to show.
 */
export default (): Promise<Announcement | null> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/announcement')
            .then(({ data }) => resolve(data.attributes || null))
            .catch(reject);
    });
};
