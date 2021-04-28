import { Component } from 'vue';
import { Field, types, localTypes } from '@/types';

export interface InterfaceConfig {
	id: string;
	name: string;
	icon: string;
	description?: string;
	component: Component;
	options: DeepPartial<Field>[] | Component;
	types: typeof types[number][];
	groups?: readonly typeof localTypes[number][];
	relational?: boolean;
	hideLabel?: boolean;
	hideLoader?: boolean;
	system?: boolean;
	recommendedDisplays?: string[];
}

export type InterfaceDefineParam = InterfaceDefineParamGeneric<InterfaceConfig>;
export type InterfaceDefineParamGeneric<T> = T | (() => T);
