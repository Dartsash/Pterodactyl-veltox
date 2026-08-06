import React, { useEffect, useState } from 'react';

interface Notification {
    id: number;
    title: string;
    message: string;
}

export default function NotificationDisplay() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchNotifications();
    }, []);

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/api/client/notifications', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            setNotifications(data.data || []);
        } catch (error) {
            console.error('Ошибка загрузки уведомлений:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading || notifications.length === 0) return null;

    return (
        <div style={{ marginBottom: '20px' }}>
            {notifications.map((notification) => (
                <div 
                    key={notification.id}
                    style={{
                        background: 'linear-gradient(135deg, #0e7490 0%, #06b6d4 100%)',
                        color: 'white',
                        padding: '15px 20px',
                        borderRadius: '6px',
                        marginBottom: '10px',
                        boxShadow: '0 4px 15px rgba(6, 182, 212, 0.3)',
                    }}
                >
                    <div style={{ fontSize: '1.2rem', fontWeight: '600' }}>
                        {notification.title}
                    </div>
                    <div style={{ fontSize: '0.95rem', marginTop: '5px', opacity: 0.95 }}>
                        {notification.message}
                    </div>
                </div>
            ))}
        </div>
    );
}
