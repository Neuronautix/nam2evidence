project = 'nam2evidence'
author = 'Neuronautix and contributors'
copyright = '2026, Neuronautix and contributors'
release = '0.1'

extensions = [
    'sphinx.ext.autosectionlabel',
]

templates_path = ['_templates']
exclude_patterns = ['_build', 'Thumbs.db', '.DS_Store']

html_theme = 'alabaster'
html_static_path = ['_static']
html_title = 'nam2evidence documentation'

autosectionlabel_prefix_document = True

rst_prolog = '''
.. role:: button
   :class: button
'''
