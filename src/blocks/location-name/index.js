import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { mapMarker } from '@wordpress/icons';
import metadata from './block.json';

function Edit({ context }) {
	const { postType } = context;
	const [placeName] = useEntityProp('postType', postType, '_geo_tagr_place');
	const blockProps = useBlockProps();

	return (
		<p {...blockProps}>
			{placeName || <em style={{ opacity: 0.5 }}>No location set</em>}
		</p>
	);
}

registerBlockType(metadata.name, {
	icon: mapMarker,
	edit: Edit,
	save: () => null,
});
