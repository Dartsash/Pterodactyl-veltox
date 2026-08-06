import React, { useState, useEffect } from 'react';

interface Permission {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface User {
    id: number;
    username: string;
    email: string;
}

export default function PermissionManager() {
    const [permissions, setPermissions] = useState<Permission[]>([]);
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [showAssign, setShowAssign] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [selectedPerm, setSelectedPerm] = useState<Permission | null>(null);
    const [userSearch, setUserSearch] = useState('');
    const [formData, setFormData] = useState({
        name: '',
        description: '',
        is_active: true,
    });

    useEffect(() => {
        fetchPermissions();
    }, []);

    const fetchPermissions = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/admin/permissions', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            setPermissions(data.data || []);
        } catch (err) {
            setError('Ошибка загрузки разрешений');
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!formData.name.trim()) {
            setError('Введите имя разрешения');
            return;
        }

        try {
            const url = editingId ? `/api/admin/permissions/${editingId}` : '/api/admin/permissions';
            const method = editingId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
            });

            const result = await response.json();
            setSuccess(result.message || 'Успешно сохранено');
            setFormData({ name: '', description: '', is_active: true });
            setEditingId(null);
            setShowForm(false);
            fetchPermissions();
        } catch (err) {
            setError('Ошибка при сохранении');
        }
    };

    const handleDelete = async (id: number) => {
        if (!confirm('Вы уверены?')) return;
        try {
            await fetch(`/api/admin/permissions/${id}`, { method: 'DELETE' });
            setSuccess('Разрешение удалено');
            fetchPermissions();
        } catch (err) {
            setError('Ошибка при удалении');
        }
    };

    const searchUsers = async (query: string) => {
        if (!query || query.length < 2) {
            setUsers([]);
            return;
        }
        try {
            const response = await fetch(`/api/admin/users/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            setUsers(data.data || []);
        } catch (err) {
            console.error(err);
        }
    };

    const handleAssignPermission = async () => {
        if (!selectedUser || !selectedPerm) {
            setError('Выберите пользователя и разрешение');
            return;
        }

        try {
            const response = await fetch(`/api/admin/users/${selectedUser.id}/permissions/${selectedPerm.id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            });

            const result = await response.json();
            setSuccess(result.message || 'Разрешение назначено');
            setSelectedUser(null);
            setSelectedPerm(null);
            setUserSearch('');
            setShowAssign(false);
        } catch (err) {
            setError('Ошибка при назначении разрешения');
        }
    };

    return (
        <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
            {error && <div style={{ background: '#f8d7da', padding: '10px', marginBottom: '10px', borderRadius: '4px', color: '#721c24' }}>{error}</div>}
            {success && <div style={{ background: '#d4edda', padding: '10px', marginBottom: '10px', borderRadius: '4px', color: '#155724' }}>{success}</div>}

            <div style={{ marginBottom: '20px' }}>
                <button onClick={() => { setShowForm(true); setEditingId(null); setFormData({name: '', description: '', is_active: true}); }} style={{ background: '#007bff', color: 'white', padding: '8px 16px', marginRight: '10px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>
                    + Новое разрешение
                </button>
                <button onClick={() => setShowAssign(true)} style={{ background: '#28a745', color: 'white', padding: '8px 16px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>
                    + Назначить разрешение
                </button>
            </div>

            {showForm && (
                <div style={{ background: '#f5f5f5', padding: '15px', marginBottom: '20px', borderRadius: '4px' }}>
                    <h3>{editingId ? 'Редактировать' : 'Создать'} разрешение</h3>
                    <form onSubmit={handleSubmit}>
                        <div style={{ marginBottom: '10px' }}>
                            <label>Имя разрешения:</label>
                            <input type="text" value={formData.name} onChange={(e) => setFormData({...formData, name: e.target.value})} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }} placeholder="Например: manage.users" required />
                        </div>
                        <div style={{ marginBottom: '10px' }}>
                            <label>Описание:</label>
                            <textarea value={formData.description} onChange={(e) => setFormData({...formData, description: e.target.value})} rows={3} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }} placeholder="Что это разрешение позволяет?" />
                        </div>
                        <div style={{ marginBottom: '10px' }}>
                            <label><input type="checkbox" checked={formData.is_active} onChange={(e) => setFormData({...formData, is_active: e.target.checked})} /> Активно</label>
                        </div>
                        <button type="submit" style={{ background: '#28a745', color: 'white', padding: '8px 16px', marginRight: '10px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Сохранить</button>
                        <button type="button" onClick={() => setShowForm(false)} style={{ background: '#6c757d', color: 'white', padding: '8px 16px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Отмена</button>
                    </form>
                </div>
            )}

            {showAssign && (
                <div style={{ background: '#f5f5f5', padding: '15px', marginBottom: '20px', borderRadius: '4px' }}>
                    <h3>Назначить разрешение</h3>
                    <div style={{ marginBottom: '10px' }}>
                        <label>Разрешение:</label>
                        <select value={selectedPerm?.id || ''} onChange={(e) => setSelectedPerm(permissions.find(p => p.id === parseInt(e.target.value)) || null)} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}>
                            <option value="">Выберите разрешение...</option>
                            {permissions.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </div>
                    <div style={{ marginBottom: '10px' }}>
                        <label>Пользователь:</label>
                        <input type="text" placeholder="Поиск по имени или email..." value={userSearch} onChange={(e) => { setUserSearch(e.target.value); searchUsers(e.target.value); }} style={{ width: '100%', padding: '8px', borderRadius: '4px', border: '1px solid #ddd', marginBottom: '5px' }} />
                        {users.length > 0 && (
                            <div style={{ maxHeight: '150px', overflowY: 'auto', border: '1px solid #ddd', borderRadius: '4px' }}>
                                {users.map(u => (
                                    <div key={u.id} onClick={() => { setSelectedUser(u); setUserSearch(u.username); setUsers([]); }} style={{ padding: '8px', borderBottom: '1px solid #ddd', cursor: 'pointer' }}>
                                        <strong>{u.username}</strong> ({u.email})
                                    </div>
                                ))}
                            </div>
                        )}
                        {selectedUser && (
                            <div style={{ background: '#d1ecf1', padding: '10px', borderRadius: '4px', marginTop: '5px' }}>
                                <strong>{selectedUser.username}</strong> ({selectedUser.email})
                            </div>
                        )}
                    </div>
                    <button onClick={handleAssignPermission} style={{ background: '#28a745', color: 'white', padding: '8px 16px', marginRight: '10px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Назначить</button>
                    <button onClick={() => { setShowAssign(false); setSelectedUser(null); setSelectedPerm(null); }} style={{ background: '#6c757d', color: 'white', padding: '8px 16px', borderRadius: '4px', border: 'none', cursor: 'pointer' }}>Отмена</button>
                </div>
            )}

            {loading ? <p>Загрузка...</p> : (
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ background: '#007bff', color: 'white' }}>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Имя</th>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Описание</th>
                            <th style={{ padding: '10px', textAlign: 'left' }}>Статус</th>
                            <th style={{ padding: '10px', textAlign: 'center' }}>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        {permissions.map(p => (
                            <tr key={p.id} style={{ borderBottom: '1px solid #ddd' }}>
                                <td style={{ padding: '10px' }}>{p.name}</td>
                                <td style={{ padding: '10px' }}>{p.description || '-'}</td>
                                <td style={{ padding: '10px' }}>{p.is_active ? '✓ Активно' : '✗ Неактивно'}</td>
                                <td style={{ padding: '10px', textAlign: 'center' }}>
                                    <button onClick={() => { setFormData({name: p.name, description: p.description || '', is_active: p.is_active}); setEditingId(p.id); setShowForm(true); }} style={{ padding: '4px 8px', marginRight: '5px', background: '#17a2b8', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer', fontSize: '12px' }}>Редактировать</button>
                                    <button onClick={() => handleDelete(p.id)} style={{ padding: '4px 8px', background: '#dc3545', color: 'white', border: 'none', borderRadius: '3px', cursor: 'pointer', fontSize: '12px' }}>Удалить</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
