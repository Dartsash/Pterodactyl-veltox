import styled from 'styled-components/macro';
import tw, { theme } from 'twin.macro';

/**
 * Horizontal tab bar used under the main navigation (server screens and the
 * account area).
 *
 * The server bar carries 13+ tabs, so it is a horizontal scroller by design.
 * The native scrollbar is hidden because `ServerSubNavigation` renders its own
 * arrow buttons and edge fades instead, which read as "there is more to the
 * right" far better than a 3px OS scrollbar sitting on top of the labels.
 */
const SubNavigation = styled.div`
    ${tw`w-full bg-surface border-b border-gray-700 overflow-x-auto overflow-y-hidden`};

    scroll-behavior: smooth;
    -ms-overflow-style: none;
    scrollbar-width: none;

    &::-webkit-scrollbar {
        display: none;
    }

    & > div {
        ${tw`flex items-center text-sm mx-auto px-2`};
        max-width: 1200px;

        & > a,
        & > div {
            ${tw`inline-flex items-center py-3 px-4 text-gray-400 no-underline whitespace-nowrap transition-all duration-150`};

            border-top: 2px solid transparent;
            border-bottom: 2px solid transparent;

            &:not(:first-of-type) {
                ${tw`ml-1`};
            }

            & > svg {
                ${tw`mr-2 opacity-50 transition-all duration-150`};
                width: 0.875rem;
                height: 0.875rem;
            }

            &:hover {
                ${tw`text-gray-100`};
                background-color: rgba(255, 255, 255, 0.04);

                & > svg {
                    ${tw`opacity-100`};
                }
            }

            &:focus {
                outline: none;
            }

            &:focus-visible {
                ${tw`text-gray-50`};
                background-color: rgba(124, 92, 255, 0.12);
            }

            &:active,
            &.active {
                ${tw`text-gray-50 font-medium`};
                border-bottom-color: ${theme`colors.primary.500`.toString()};
                background-color: rgba(124, 92, 255, 0.08);

                & > svg {
                    ${tw`opacity-100 text-primary-400`};
                }
            }
        }
    }
`;

export default SubNavigation;
