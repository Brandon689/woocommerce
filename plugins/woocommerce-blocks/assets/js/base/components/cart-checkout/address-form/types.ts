/**
 * External dependencies
 */
import type {
	AddressField,
	AddressFields,
	AddressType,
	ShippingAddress,
	KeyedAddressField,
} from '@woocommerce/settings';

export type FieldConfig = Record<
	keyof AddressFields,
	Partial< AddressField >
>;

export type FieldType = keyof AddressFields;

export type AddressFormFields = {
	fields: KeyedAddressField[];
	addressType: AddressType;
	required: KeyedAddressField[];
	hidden: KeyedAddressField[];
};

export interface AddressFormProps {
	// Id for component.
	id?: string;
	// Type of form (billing or shipping).
	addressType?: AddressType;
	// Array of fields in form.
	fields: FieldType[];
	// Field configuration for fields in form.
	fieldConfig?: FieldConfig;
	// Called with the new address data when the address form changes. This is only called when all required fields are filled and there are no validation errors.
	onChange: ( newValue: ShippingAddress ) => void;
	// Values for fields.
	values: ShippingAddress;
}
