import { useState, useEffect } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function RelationshipControl({ postType, metaKey, label, value, onChange, multiple = false }) {
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!value || (Array.isArray(value) && value.length === 0)) {
            setOptions([]);
            return;
        }
        setLoading(true);
        // Fetch titles for the selected IDs to display
        const ids = Array.isArray(value) ? value : [value];
        Promise.all(ids.map(id =>
            apiFetch({ path: `/wp/v2/${postType}/${id}` }).catch(() => null)
        )).then(posts => {
            const validPosts = posts.filter(p => p);
            setOptions(validPosts.map(p => ({ label: p.title.rendered, value: p.id })));
            setLoading(false);
        });
    }, [value, postType]);

    // Handler for selection change
    function onSelectChange(selected) {
        if (multiple) {
            onChange(selected ? selected.map(item => item.value) : []);
        } else {
            onChange(selected ? selected.value : 0);
        }
    }

    return (
        <SelectControl
            label={label}
            multiple={multiple}
            value={
                multiple
                    ? options.filter(opt => (value || []).includes(opt.value))
                    : options.find(opt => opt.value === value) || null
            }
            options={options}
            onChange={onSelectChange}
            isLoading={loading}
            // For async search, you'd need a more advanced control like Combobox or custom Select2 wrapper
        />
    );
}
