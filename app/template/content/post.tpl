import(common.tpl)

head > title                            = $this->post['title']
head > meta[name="keywords"]|content    = $this->post['meta_keywords']
head > meta[name="description"]|content = $this->post['meta_description']
head > meta[property="og:title"]|content       = $this->post['title']
head > meta[property="og:description"]|content = $this->post['meta_description']