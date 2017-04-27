export const mapStateToProps = (state, props) => ({
    ...state.tabs.filter(({editorData, node}) => editorData && editorData.id === props.editorData.id && node.getLabel() === props.node.getLabel())[0],
    ...props
})
