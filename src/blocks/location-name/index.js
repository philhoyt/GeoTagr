import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useEntityProp } from '@wordpress/core-data';
import { mapMarker } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit({ context }) {
	const { postType, postId } = context;
	// Registered meta lives under the post record's `meta` property; pass the
	// context post ID so the preview is correct inside Query Loop templates.
	const [meta] = useEntityProp('postType', postType, 'meta', postId);
	const placeName = meta?._geo_tagr_place;
	const blockProps = useBlockProps();

	return (
		<p {...blockProps}>
			{placeName || (
				<em style={{ color: '#757575' }}>
					{__('No location set', 'geotagr')}
				</em>
			)}
		</p>
	);
}

registerBlockType(metadata.name, {
	icon: mapMarker,
	edit: Edit,
	save: () => null,
});
