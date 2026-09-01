
// Vvveb resolves a page template by CONTROLLER, not by html template name, so
// every URL served by this controller compiles against this .tpl. Plugin
// template directories are never on the vtpl template path, so importing the
// plugin rule file here is what binds the solutions-directory component output
// into the rendered page.
//
// The leading blank line is required: Vtpl::loadTemplateFileFromPath()
// concatenates the core tpl and this one with no separator, and the import
// regex is line-greedy, so without it the core import would swallow this one.
import(/plugins/solutions-directory/app/template/solutions.tpl)

