// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for core_course/ActivityIcon.
 *
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, render} from '@testing-library/react';
import ActivityIcon from '@moodle/lms/core_course/ActivityIcon';

// @moodlehq/design-system is ESM only, so Jest cannot resolve it from a CommonJS test bundle.
// Stand ActivityIcon in with markup exposing the props it receives.
jest.mock('@moodlehq/design-system', () => ({
    ActivityIcon: ({icon, alt, container, size}: {icon: string; alt: string; container: string; size: string}) => (
        <span data-testid="ds-icon" data-icon={icon} data-alt={alt} data-container={container} data-size={size} />
    ),
}), {virtual: true});

type MediaListener = (event: {matches: boolean}) => void;

/**
 * Stand in for window.matchMedia, which jsdom does not implement.
 *
 * @param matches Whether the media query matches.
 * @returns A function to change whether the media query matches.
 */
const mockMatchMedia = (matches: boolean) => {
    const listeners: MediaListener[] = [];
    window.matchMedia = jest.fn().mockImplementation((query: string) => ({
        matches,
        media: query,
        addEventListener: (_: string, listener: MediaListener) => listeners.push(listener),
        removeEventListener: jest.fn(),
    }));
    return (value: boolean) => listeners.forEach((listener) => listener({matches: value}));
};

describe('core_course/ActivityIcon', () => {
    beforeEach(() => {
        mockMatchMedia(false);
    });

    it('passes the icon through with the card defaults', () => {
        const {getByTestId} = render(<ActivityIcon icon="quiz" />);
        const icon = getByTestId('ds-icon');
        expect(icon.dataset.icon).toBe('quiz');
        expect(icon.dataset.alt).toBe('');
        expect(icon.dataset.container).toBe('default');
        expect(icon.dataset.size).toBe('md');
    });

    it('passes the container, size and alt props through', () => {
        const {getByTestId} = render(<ActivityIcon icon="forum" alt="Forum" container="none" size="lg" />);
        const icon = getByTestId('ds-icon');
        expect(icon.dataset.alt).toBe('Forum');
        expect(icon.dataset.container).toBe('none');
        expect(icon.dataset.size).toBe('lg');
    });

    it('uses the mobile size on small screens', () => {
        mockMatchMedia(true);
        const {getByTestId} = render(<ActivityIcon icon="quiz" mobileSize="sm" />);
        expect(getByTestId('ds-icon').dataset.size).toBe('sm');
    });

    it('keeps the size on large screens', () => {
        const {getByTestId} = render(<ActivityIcon icon="quiz" mobileSize="sm" />);
        expect(getByTestId('ds-icon').dataset.size).toBe('md');
    });

    it('keeps the size on small screens when there is no mobile size', () => {
        mockMatchMedia(true);
        const {getByTestId} = render(<ActivityIcon icon="quiz" />);
        expect(getByTestId('ds-icon').dataset.size).toBe('md');
    });

    it('follows the screen size changes', () => {
        const setMatches = mockMatchMedia(false);
        const {getByTestId} = render(<ActivityIcon icon="quiz" mobileSize="sm" />);
        expect(getByTestId('ds-icon').dataset.size).toBe('md');

        act(() => setMatches(true));
        expect(getByTestId('ds-icon').dataset.size).toBe('sm');
    });
});
