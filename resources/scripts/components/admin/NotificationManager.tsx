import React, { useState, useEffect } from 'react';

interface Notification {
    id: number;
    title: string;
    message: string;
    is_active: boolean;
    admin_id: number;
    admin?: { username: string };
    created_at: string;
}

export default function NotificationManager() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [formData, setFormData] = useState({
        title: '',
        message: '',
        is_active: true,
    });
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        fetchNotifications();
    }, []);

    const fetchNotifications = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/admin/notifications', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            setNotifications(data.data || []);
            setError('');
        } catch (err) {
            setError('Ошибка загрузки уведомлений');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!formData.title.trim() || !formData.message.trim()) {
            setError('Заполните все поля');
            return;
        }

        try {
            const url = editingId ? `/api/admin/notifications/${editingId}` : '/api/admin/notifications';
            const method = editingId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(formData),
            });

            const result = await response.json();
            setSuccess(result.message || 'Успешно сохранено');
            setFormData({ title: '', message: '', is_active: true });
            setEditingId(null);
            setShowForm(false);
            fetchNotifications();
        } catch (err) {
            setError('Ошибка при сохранении');
        }
    };

    const handleEdit = (notification: Notification) => {
        setFormData({
            title: notification.title,
            message: notification.message,
            is_active: notification.is_active,
        });
        setEditingId(notification.id);
        setShowForm(true);
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Вы уверены?')) return;
        try {
            await fetch(`/api/admin/notifications/${id}`, { method: 'DELETE' });
            setSuccess('Уведомление удалено');
            fetchNotifications();
        } catch (err) {
            setError('Ошибка при удалении');
        }
    };

    const handleToggle = async (id: number) => {
        try {
            await fetch(`/api/admin/notifications/${id}/toggle`, { method: 'POST' });
            fetchNotifications();
        } catch (err) {
            setError('Ошибка при изменении статуса');
        }
    };

    return (
        <div style={{ maxWidth: '1000px', margin: '0 auto' }}>
            {error && <div style={{ background: '#f8d7da', padding: '10px', marginBottom: '10px', borderRadius: '4px', color: '#721c24' }}>{error}</div>}
            {success && <div style={{ background: '#d4edda', padding: '10px', marginBottom: '10px', borderRadius: '4px', color: '#155724' }}>{success}</div>}

            {!showForm && (
                <button onClick={() => setShowForm(true)} style={{ background: '#007bff', color: 'white', padding: '8px 16px', borderRadius: '4px', border: 'none', cursor: 'pointer', marginBottom: '15px' }}>
                    + Новое уведомление
                </button>
            )}

            {showForm && (
                <div style={{ background: '#f5f5f5', padding: '15px', marginBottom: '20px', borderRadius: '4px' }}>
                    <h3>{editingId ? 'Редактировать' : 'Создать'} уведомление</h3>
                    <form onSubmit={handleSubmit}>
                        <div style={{ marginBottom: '10px' }}>
                            <label>Название:</label>
                            <input type="text" value={formData.title} onChange={(e) => setFormData({...formData, title: e.target.value})} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }} placeholder="Например: Good evening, dartsash" required />
                        </div>
                        <div style={{ marginBottom: '10px' }}>
                            <label>Сообщение:</label>
                            <textarea value={formData.message} onChange={(e) => setFormData({...formData, message: e.target.value})} rows={4} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }} placeholder="Текст уведомления" required />
                        </div>
                        <div style={{ marginBottom: '10px' }}>
                            <label>
                                <input type="checkbox" checked={formData.is_active} onChange={(e) => setFormData({...formData, is_active: e.target.checked})} />
                                Активно
                            </label>
                        </div>
                        <button type="submit" style={{ background: '#28a745', color: 'white', padding: '8px 16px', marginRight: '10px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Сохранить</button>
                        <button type="button" onClick={() => { setShowForm(false); setEditingId(null); }} style={{ background: '#6c757d', color: 'white', padding: '8px 16px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Отмена</button>
                    </form>
                </div>
            )}

            {loading ? <p>Загрузка...</p> : (
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#007bff', color: 'white' }}>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Название</th>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Сообщение</th>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Статус</th>
                            <th style={{ padding: '10px', textAlign: 'center' }}>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        {notifications.map(n => (
                            <tr key={n.id} style={{ borderBottom: '1px solid #ddd' }}>
                                <td style={{ padding: '10px' }}>{n.title}</td>
                                <td style={{ padding: '10px' }}>{n.message.substring(0, 50)}...</td>
                                <td style={{ padding: '10px' }}>{n.is_active ? '✓ Активно' : '✗ Неактивно'}</td>
                                <td style={{ padding: '10px', textAlign: 'center' }}>
                                    <button onClick={() => handleToggle(n.id)} style={{ padding: '4px 8px', marginRight: '5px', background: n.is_active ? '#ffc107' : '#28a745', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer', fontSize: '12px' }}>
                                        {n.is_active ? 'Отключить' : 'Включить'}
                                    </button>
                                    <button onClick={() => handleEdit(n)} style={{ padding: '4px 8px', marginRight: '5px', background: '#17a2b8', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer', fontSize: '12px' }}>Редактировать</button>
                                    <button onClick={() => handleDelete(n.id)} style={{ padding: '4px 8px', background: '#dc3545', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer', fontSize: '12px' }}>Удалить</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
