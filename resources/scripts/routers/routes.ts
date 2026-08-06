import React, { lazy } from 'react';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faArchive,
    faCalendarAlt,
    faCog,
    faCubes,
    faDatabase,
    faFolder,
    faHistory,
    faKey,
    faLayerGroup,
    faNetworkWired,
    faPlay,
    faPuzzlePiece,
    faSlidersH,
    faTerminal,
    faUser,
    faUserShield,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import ServerConsole from '@/components/server/console/ServerConsoleContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import StartupContainer from '@/components/server/startup/StartupContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import SettingsContainer from '@/components/server/settings/SettingsContainer';
import AccountOverviewContainer from '@/components/dashboard/AccountOverviewContainer';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import ActivityLogContainer from '@/components/dashboard/activity/ActivityLogContainer';
import ServerActivityLogContainer from '@/components/server/ServerActivityLogContainer';
import AddonsContainer from '@/components/server/addons/AddonsContainer';
import PropertiesContainer from '@/components/server/properties/PropertiesContainer';
import VersionManagerContainer from '@/components/server/versions/VersionManagerContainer';
import PlayerManagerContainer from '@/components/server/players/PlayerManagerContainer';
import ModInstallerContainer from '@/components/server/mods/ModInstallerContainer';

// Each of the router files is already code split out appropriately — so
// all of the items above will only be loaded in when that router is loaded.
//
// These specific lazy loaded routes are to avoid loading in heavy screens
// for the server dashboard when they're only needed for specific instances.
const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));

interface RouteDefinition {
    path: string;
    // If undefined is passed this route is still rendered into the router itself
    // but no navigation link is displayed in the sub-navigation menu.
    name: string | undefined;
    component: React.ComponentType;
    exact?: boolean;
    // Rendered next to the name in the sub-navigation. Purely decorative: the
    // label stays visible, the icon just makes a long tab bar scannable.
    icon?: IconDefinition;
}

interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
}

interface Routes {
    // All of the routes available under "/account"
    account: RouteDefinition[];
    // All of the routes available under "/server/:id"
    server: ServerRouteDefinition[];
}

export default {
    account: [
        {
            path: '/',
            name: 'Account',
            component: AccountOverviewContainer,
            exact: true,
            icon: faUser,
        },
        {
            path: '/api',
            name: 'API Credentials',
            component: AccountApiContainer,
            icon: faKey,
        },
        {
            path: '/ssh',
            name: 'SSH Keys',
            component: AccountSSHContainer,
            icon: faTerminal,
        },
        {
            path: '/activity',
            name: 'Activity',
            component: ActivityLogContainer,
            icon: faHistory,
        },
    ],
    server: [
        {
            path: '/',
            permission: null,
            name: 'Console',
            component: ServerConsole,
            exact: true,
            icon: faTerminal,
        },
        {
            path: '/files',
            permission: 'file.*',
            name: 'Files',
            component: FileManagerContainer,
            icon: faFolder,
        },
        {
            path: '/files/:action(edit|new)',
            permission: 'file.*',
            name: undefined,
            component: FileEditContainer,
        },
        {
            path: '/databases',
            permission: 'database.*',
            name: 'Databases',
            component: DatabasesContainer,
            icon: faDatabase,
        },
        {
            path: '/schedules',
            permission: 'schedule.*',
            name: 'Schedules',
            component: ScheduleContainer,
            icon: faCalendarAlt,
        },
        {
            path: '/schedules/:id',
            permission: 'schedule.*',
            name: undefined,
            component: ScheduleEditContainer,
        },
        {
            path: '/users',
            permission: 'user.*',
            name: 'Users',
            component: UsersContainer,
            icon: faUsers,
        },
        {
            path: '/backups',
            permission: 'backup.*',
            name: 'Backups',
            component: BackupContainer,
            icon: faArchive,
        },
        {
            path: '/network',
            permission: 'allocation.*',
            name: 'Network',
            component: NetworkContainer,
            icon: faNetworkWired,
        },
        {
            path: '/startup',
            permission: 'startup.*',
            name: 'Startup',
            component: StartupContainer,
            icon: faPlay,
        },
        {
            path: '/addons',
            permission: 'addon.read',
            name: 'Plugins',
            component: AddonsContainer,
            icon: faPuzzlePiece,
        },
        {
            path: '/versions',
            permission: 'file.create',
            name: 'Versions',
            component: VersionManagerContainer,
            icon: faCubes,
        },
        {
            path: '/players',
            permission: 'file.*',
            name: 'Players',
            component: PlayerManagerContainer,
            icon: faUserShield,
        },
        {
            path: '/mods',
            permission: 'file.*',
            name: 'Mods',
            component: ModInstallerContainer,
            icon: faLayerGroup,
        },
        {
            path: '/properties',
            permission: 'file.*',
            name: 'Configuration',
            component: PropertiesContainer,
            icon: faSlidersH,
        },
        {
            path: '/settings',
            permission: ['settings.*', 'file.sftp'],
            name: 'Settings',
            component: SettingsContainer,
            icon: faCog,
        },
        {
            path: '/activity',
            permission: 'activity.*',
            name: 'Activity',
            component: ServerActivityLogContainer,
            icon: faHistory,
        },
    ],
} as Routes;
