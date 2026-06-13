import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ReviewsTable from './ReviewsTable.vue';

const meta = { current_page: 1, per_page: 50, total: 60, last_page: 2 };

describe('ReviewsTable', () => {
    it('renders author date text and rating', () => {
        const wrapper = mount(ReviewsTable, {
            props: {
                reviews: [{
                    id: 1,
                    author_name: 'Иван',
                    review_date: '2026-01-10',
                    text: 'Отлично',
                    rating: 5,
                }],
                meta,
            },
        });

        expect(wrapper.text()).toContain('Иван');
        expect(wrapper.text()).toContain('2026-01-10');
        expect(wrapper.text()).toContain('Отлично');
        expect(wrapper.text()).toContain('5');
    });

    it('emits page change from pagination', async () => {
        const wrapper = mount(ReviewsTable, {
            props: {
                reviews: [{ id: 1, author_name: 'Иван', text: 'Отлично' }],
                meta,
            },
        });

        const buttons = wrapper.findAll('button');
        await buttons[1].trigger('click');

        expect(wrapper.emitted('page-change')[0]).toEqual([2]);
    });
});
