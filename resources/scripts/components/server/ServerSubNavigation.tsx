import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { NavLink } from 'react-router-dom';
import { useLocation } from 'react-router';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronLeft, faChevronRight, faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import Can from '@/components/elements/Can';
import SubNavigation from '@/components/elements/SubNavigation';
import routes from '@/routers/routes';

interface Props {
    /** Builds the target URL for a route path, provided by ServerRouter. */
    to: (value: string, url?: boolean) => string;
    rootAdmin: boolean;
    serverId?: number;
    /** Forwarded by the CSSTransition wrapper in ServerRouter (fade classes). */
    className?: string;
}

/**
 * The tab bar shown on every server screen.
 *
 * With 13 tabs the bar overflows on anything narrower than a desktop, and the
 * old version just left a hidden native scrollbar: on a laptop or phone the
 * last tabs (Settings, Activity) were invisible with no hint that they existed.
 *
 * This version:
 *  - keeps the active tab scrolled into view after every navigation,
 *  - renders arrow buttons + edge fades only while there is something to scroll,
 *  - supports the mouse wheel for horizontal scrolling,
 *  - pins the "open in admin" link to the far right instead of letting it float
 *    directly after the last tab.
 */
export default ({ to, rootAdmin, serverId, className }: Props) => {
    const location = useLocation();
    const scroller = useRef<HTMLDivElement>(null);

    const [overflow, setOverflow] = useState({ left: false, right: false });

    const measure = useCallback(() => {
        const element = scroller.current;

        if (!element) {
            return;
        }

        // 4px of slack, otherwise sub-pixel widths keep an arrow permanently on.
        setOverflow({
            left: element.scrollLeft > 4,
            right: element.scrollLeft + element.clientWidth < element.scrollWidth - 4,
        });
    }, []);

    useLayoutEffect(() => {
        measure();

        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, [measure]);

    // Center the tab the user just navigated to, including on a cold page load
    // straight into e.g. /server/x/activity.
    useEffect(() => {
        const active = scroller.current?.querySelector('a.active');

        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ block: 'nearest', inline: 'center' });
        }

        measure();
    }, [location.pathname, measure]);

    const nudge = (direction: -1 | 1) => {
        const element = scroller.current;

        if (!element) {
            return;
        }

        element.scrollBy({ left: direction * Math.max(160, element.clientWidth * 0.6), behavior: 'smooth' });
    };

    const onWheel = (event: React.WheelEvent<HTMLDivElement>) => {
        const element = scroller.current;

        // Only hijack a vertical wheel when this bar actually scrolls sideways,
        // so the page keeps scrolling normally on wide screens.
        if (!element || element.scrollWidth <= element.clientWidth || event.deltaY === 0) {
            return;
        }

        event.preventDefault();
        element.scrollLeft += event.deltaY;
    };

    const arrow = (side: 'left' | 'right') => (
        <button
            type={'button'}
            tabIndex={-1}
            aria-hidden={true}
            onClick={() => nudge(side === 'left' ? -1 : 1)}
            css={[
                tw`absolute top-0 bottom-0 z-10 flex items-center justify-center w-8 text-gray-300`,
                tw`bg-surface hover:text-gray-50 transition-colors duration-150`,
                side === 'left' ? tw`left-0 border-r border-gray-700` : tw`right-0 border-l border-gray-700`,
            ]}
        >
            <FontAwesomeIcon icon={side === 'left' ? faChevronLeft : faChevronRight} css={tw`w-3 h-3`} />
        </button>
    );

    return (
        <div className={className} css={tw`relative`}>
            {overflow.left && arrow('left')}

            <SubNavigation ref={scroller} onScroll={measure} onWheel={onWheel}>
                <div>
                    {routes.server
                        .filter((route) => !!route.name)
                        .map((route) => {
                            const link = (
                                <NavLink to={to(route.path, true)} exact={route.exact}>
                                    {route.icon && <FontAwesomeIcon icon={route.icon} />}
                                    {route.name}
                                </NavLink>
                            );

                            return route.permission ? (
                                <Can key={route.path} action={route.permission} matchAny>
                                    {link}
                                </Can>
                            ) : (
                                <React.Fragment key={route.path}>{link}</React.Fragment>
                            );
                        })}
                    {rootAdmin && (
                        // eslint-disable-next-line react/jsx-no-target-blank
                        <a
                            href={`/admin/servers/view/${serverId}`}
                            target={'_blank'}
                            title={'Manage this server in the admin area'}
                            // Inline style: the parent stylesheet targets `& > a`
                            // with higher specificity than a css-prop class.
                            style={{ marginLeft: 'auto' }}
                        >
                            <FontAwesomeIcon icon={faExternalLinkAlt} />
                        </a>
                    )}
                </div>
            </SubNavigation>

            {overflow.right && arrow('right')}
        </div>
    );
};
