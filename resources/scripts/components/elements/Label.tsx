import styled from 'styled-components/macro';
import tw from 'twin.macro';

const Label = styled.label<{ isLight?: boolean }>`
    ${tw`block text-2xs font-bold uppercase tracking-wider text-gray-400 mb-1 sm:mb-2`};
    ${(props) => props.isLight && tw`text-gray-700`};
`;

export default Label;
