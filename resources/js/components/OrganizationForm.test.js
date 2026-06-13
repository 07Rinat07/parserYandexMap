import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OrganizationForm from './OrganizationForm.vue';

describe('OrganizationForm', () => {
    it('does not submit an empty url', async () => {
        const wrapper = mount(OrganizationForm);

        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.emitted('save')).toBeUndefined();
        expect(wrapper.text()).toContain('Вставьте ссылку');
    });
});
