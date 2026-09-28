import Application from './Application';
import cssVarPlugin from './plugins/cssVar';
import VueApplication from "@/components/Application";

export default window.FLUENT_EVENT_APP = Application.create(
	VueApplication, [
		cssVarPlugin,
	]
);
