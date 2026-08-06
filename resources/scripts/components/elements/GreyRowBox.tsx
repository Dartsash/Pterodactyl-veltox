import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex rounded-lg no-underline text-gray-200 items-center bg-surface p-4 border border-gray-700 transition-all duration-150 overflow-hidden`};

    ${(props) => props.$hoverable !== false && tw`hover:bg-raised hover:border-primary-500`};

    & .icon {
        ${tw`rounded-lg w-12 h-12 flex-none flex items-center justify-center bg-raised text-gray-300 p-3`};
    }
`;
