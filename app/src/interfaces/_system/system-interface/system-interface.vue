<template>
	<v-notice v-if="selectedType === undefined">
		{{ $t('select_field_type') }}
	</v-notice>
	<v-select
		v-else
		:items="items"
		@input="$listeners.input"
		:value="value"
		:placeholder="$t('interfaces.system-interface.placeholder')"
	/>
</template>

<script lang="ts">
import { defineComponent, computed, inject, ref, watch } from '@vue/composition-api';
import { getInterfaces } from '@/interfaces';
import { InterfaceConfig } from '@/interfaces/types';

export default defineComponent({
	props: {
		value: {
			type: String,
			default: null,
		},
		typeField: {
			type: String,
			default: null,
		},
	},
	setup(props, { emit }) {
		const { interfaces } = getInterfaces();

		const values = inject('values', ref<Record<string, any>>({}));

		const selectedType = computed(() => {
			if (props.typeField === null || !values.value[props.typeField]) return;
			return values.value[props.typeField];
		});

		watch(
			() => values.value[props.typeField],
			() => {
				emit('input', null);
			}
		);

		const items = computed(() => {
			return interfaces.value
				.filter((inter: InterfaceConfig) => inter.relational !== true && inter.system !== true)
				.filter(
					(inter: InterfaceConfig) => selectedType.value === undefined || inter.types.includes(selectedType.value)
				)
				.map((inter: InterfaceConfig) => {
					return {
						text: inter.name,
						value: inter.id,
					};
				});
		});

		return { items, selectedType, values };
	},
});
</script>
